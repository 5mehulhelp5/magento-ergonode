<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\ProductPublisher\Api\Data\ProductCreationContextInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\GraphQl\RemoteProductPublicationStateLoader;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationMutationPlanner;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ProductPublicationComparisonTest extends TestCase
{
    public function testTwentyProductPlanRemoves2374AbsentClearsAndTwentyMatchingTemplates(): void
    {
        $states = [];
        $remote = [];
        for ($index = 0; $index < 20; ++$index) {
            $values = [];
            for ($attribute = 0; $attribute < ($index < 14 ? 119 : 118); ++$attribute) {
                $values[] = new ProductAttributeValue(
                    'empty_' . $attribute,
                    'text',
                    [],
                    clearedLanguageCodes: ['pl_PL']
                );
            }
            for ($attribute = 0; $attribute < ($index < 16 ? 4 : 3); ++$attribute) {
                $values[] = new ProductAttributeValue('filled_' . $attribute, 'text', ['pl_PL' => 'value']);
            }
            $sku = 'sku-' . $index;
            $states[$sku] = new ProductState($sku, 'simple', 'template-' . ($index % 3), values: $values);
            $remote['sku:' . $sku] = ['template' => 'template-' . ($index % 3), 'translations' => []];
        }
        $loader = $this->createMock(RemoteProductPublicationStateLoader::class);
        $loader->expects(self::once())->method('load')->with(array_keys($states))->willReturn($remote);
        $planner = new ProductPublicationMutationPlanner(new ProductMutationFactory(), $loader);
        $updateBase = array_fill_keys(array_keys($states), true);
        $before = $planner->operations($states, $updateBase, compareRemoteState: false);
        $after = $planner->operations($states, $updateBase);
        $batchPlanner = new MutationBatchPlanner(new MutationBatchBuilder(new MutationAliasGenerator(), new Json()));

        self::assertCount(2470, $before);
        self::assertCount(50, $batchPlanner->plan($before));
        self::assertCount(76, $after);
        self::assertCount(2, $batchPlanner->plan($after));
        self::assertSame(
            ['productAddAttributeValueTranslationsText'],
            array_values(array_unique($this->fields($after)))
        );
    }

    public function testExistingTranslationsAreClearedOnlyForRequestedLanguagesAndWritesRetainZeroAndLists(): void
    {
        $state = new ProductState('001', 'simple', 'default', values: [
            new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL', 'en_GB']),
            new ProductAttributeValue('empty', 'text', [], clearedLanguageCodes: ['pl_PL']),
            new ProductAttributeValue('number', 'numeric', ['pl_PL' => 0]),
            new ProductAttributeValue('options', 'multi_select', ['pl_PL' => []]),
        ]);
        $loader = $this->createStub(RemoteProductPublicationStateLoader::class);
        $loader->method('load')->willReturn([
            'sku:001' => ['template' => 'default', 'translations' => ['title' => ['pl_PL' => true, 'de_DE' => true]]],
        ]);
        $operations = (new ProductPublicationMutationPlanner(new ProductMutationFactory(), $loader))
            ->operations(['sku:001' => $state], ['sku:001' => true]);

        self::assertSame([
            'productDeleteAttributeValueTranslations',
            'productAddAttributeValueTranslationsNumeric',
            'productAddAttributeValueTranslationsMultiSelect',
        ], $this->fields($operations));
        self::assertSame(['pl_PL'], $operations[0]->getVariables()['input']->getValue()['languages']);
        self::assertSame(0.0, $operations[1]->getVariables()['input']->getValue()['translations'][0]['value']);
        self::assertSame([], $operations[2]->getVariables()['input']->getValue()['translations'][0]['value']);
    }

    public function testUnknownRemoteStateAndChangedTemplatePreserveAllWrites(): void
    {
        $state = new ProductState('sku', 'simple', 'new', values: [
            new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL']),
        ]);
        foreach ([[], ['sku:sku' => ['template' => 'old', 'translations' => []]]] as $remote) {
            $loader = $this->createStub(RemoteProductPublicationStateLoader::class);
            $loader->method('load')->willReturn($remote);
            self::assertSame([
                'productSetTemplate', 'productDeleteAttributeValueTranslations',
            ], $this->fields((new ProductPublicationMutationPlanner(new ProductMutationFactory(), $loader))
                ->operations(['sku' => $state], ['sku' => true])));
        }
    }

    public function testNewProductAndRecoveryDoNotUseRemoteAbsenceToSkipClears(): void
    {
        $value = new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL']);
        $new = new ProductState('new', 'simple', 'default', values: [$value]);
        $created = $this->createStub(ProductCreationContextInterface::class);
        $created->method('getSku')->willReturn('created');
        $created->method('getTemplateCode')->willReturn('default');
        $created->method('getValues')->willReturn([$value]);
        $created->method('wasCreatedInCurrentSynchronization')->willReturn(true);
        $loader = $this->createMock(RemoteProductPublicationStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')->with([])->willReturn([]);
        $planner = new ProductPublicationMutationPlanner(new ProductMutationFactory(), $loader);

        self::assertSame(
            ['productDeleteAttributeValueTranslations'],
            $this->fields($planner->operations(['new' => $new], []))
        );
        self::assertSame(['productSetTemplate', 'productDeleteAttributeValueTranslations'], $this->fields(
            $planner->operations(['created' => $created], ['created' => true])
        ));
        self::assertSame(['productSetTemplate', 'productDeleteAttributeValueTranslations'], $this->fields(
            $planner->operations(['new' => $new], ['new' => true], compareRemoteState: false)
        ));
    }

    public function testRemoteChangeBetweenPublicationsIsObservedAgain(): void
    {
        $state = new ProductState('sku', 'simple', 'default', values: [
            new ProductAttributeValue('title', 'text', [], clearedLanguageCodes: ['pl_PL']),
        ]);
        $loader = $this->createMock(RemoteProductPublicationStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')->willReturnOnConsecutiveCalls(
            ['sku:sku' => ['template' => 'default', 'translations' => []]],
            ['sku:sku' => ['template' => 'default', 'translations' => ['title' => ['pl_PL' => true]]]]
        );
        $planner = new ProductPublicationMutationPlanner(new ProductMutationFactory(), $loader);
        self::assertSame([], $planner->operations(['sku' => $state], ['sku' => true]));
        self::assertSame(
            ['productDeleteAttributeValueTranslations'],
            $this->fields($planner->operations(['sku' => $state], ['sku' => true]))
        );
    }

    private function fields(array $operations): array
    {
        return array_map(static fn ($operation): string => $operation->getField(), $operations);
    }
}
