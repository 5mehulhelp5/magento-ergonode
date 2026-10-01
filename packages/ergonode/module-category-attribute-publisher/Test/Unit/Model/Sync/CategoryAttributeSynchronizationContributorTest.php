<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributePublisher\Model\Data\CategoryAttributeStateDto;
use Ergonode\CategoryAttributePublisher\Model\Data\CategoryAttributeValue;
use Ergonode\CategoryAttributePublisher\Model\GraphQl\CategoryAttributeMutationFactory;
use Ergonode\CategoryAttributePublisher\Model\Sync\CategoryAttributeStateLoader;
use Ergonode\CategoryAttributePublisher\Model\Sync\CategoryAttributeSynchronizationContributor;
use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Model\Data\CategoryStateDto;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryAttributeSynchronizationContributorTest extends TestCase
{
    public function testInvalidBatchDataFallsBackToIsolatedCategoryPlanning(): void
    {
        $invalid = new CategoryStateDto('invalid', [], [new CategoryAttributeStateDto()]);
        $valid = new CategoryStateDto('valid', [], [new CategoryAttributeStateDto()]);
        $loader = $this->createMock(CategoryAttributeStateLoader::class);
        $loader->expects(self::once())->method('loadBatch')
            ->willThrowException(new LocalizedException(__('Unsupported value.')));
        $loader->expects(self::exactly(2))->method('load')->willReturnCallback(
            static function (string $code): CategoryAttributeStateDto {
                if ($code === 'invalid') {
                    throw new LocalizedException(__('Unsupported value.'));
                }
                return new CategoryAttributeStateDto();
            }
        );
        $mode = CategorySynchronizerInterface::MODE_UPDATE;
        $prepared = $this->contributor($loader)->forBatch(['invalid' => $invalid, 'valid' => $valid], $mode);
        try {
            $prepared->planNext($invalid, $mode);
            self::fail('Invalid category must retain its failure.');
        } catch (LocalizedException $exception) {
            self::assertSame('Unsupported value.', $exception->getMessage());
        }
        self::assertSame([], $prepared->planNext($valid, $mode));
    }

    public function testTransportFailureNeverFansOutIntoIndividualReads(): void
    {
        $loader = $this->createMock(CategoryAttributeStateLoader::class);
        $exception = new GraphQlRequestException('Rate limited.', GraphQlRequestException::FAILURE_RATE_LIMIT);
        $loader->expects(self::once())->method('loadBatch')->willThrowException($exception);
        $loader->expects(self::never())->method('load');
        $this->expectExceptionObject($exception);
        $this->contributor($loader)->forBatch([], CategorySynchronizerInterface::MODE_UPDATE);
    }

    public function testDoesNothingWhenCategoryHasNoAttributeContribution(): void
    {
        $loader = $this->createMock(CategoryAttributeStateLoader::class);
        $loader->expects(self::never())->method('load');

        self::assertSame([], $this->contributor($loader)->planNext(
            new CategoryStateDto('chairs'),
            CategorySynchronizerInterface::MODE_UPDATE
        ));
    }

    public function testAddsRequiredAttributeBeforePublishingItsValue(): void
    {
        $value = new CategoryAttributeValue('description', 'text', ['pl_PL' => 'Opis']);
        $desired = new CategoryStateDto(
            'chairs',
            [],
            [new CategoryAttributeStateDto([], [$value])]
        );
        $loader = $this->createMock(CategoryAttributeStateLoader::class);
        $loader->expects(self::once())->method('load')->with('chairs', ['pl_PL'])
            ->willReturn(new CategoryAttributeStateDto());

        $operations = $this->contributor($loader)->planNext(
            $desired,
            CategorySynchronizerInterface::MODE_UPDATE
        );

        self::assertSame('categoryAttributeAddAttribute', $operations[0]->getField());
    }

    public function testPublishesMappedValueWhenAttributeIsAlreadyAllowed(): void
    {
        $value = new CategoryAttributeValue('description', 'text', ['pl_PL' => 'Opis']);
        $desired = new CategoryStateDto(
            'chairs',
            [],
            [new CategoryAttributeStateDto(['description'], [$value])]
        );
        $loader = $this->createMock(CategoryAttributeStateLoader::class);
        $loader->method('load')->willReturn(new CategoryAttributeStateDto(['description']));

        $operations = $this->contributor($loader)->planNext(
            $desired,
            CategorySynchronizerInterface::MODE_UPDATE
        );

        self::assertSame('categoryAddAttributeValueTranslationsText', $operations[0]->getField());
    }

    public function testReconcileRemovesOnlyRemoteValueTranslationsNotPresentInDesiredState(): void
    {
        $desiredValue = new CategoryAttributeValue('description', 'text', ['pl_PL' => 'Opis']);
        $remoteValue = new CategoryAttributeValue(
            'description',
            'text',
            ['pl_PL' => 'Opis', 'en_GB' => 'Description']
        );
        $desired = new CategoryStateDto(
            'chairs',
            [],
            [new CategoryAttributeStateDto(['description'], [$desiredValue])]
        );
        $loader = $this->createMock(CategoryAttributeStateLoader::class);
        $loader->expects(self::once())->method('load')->with('chairs', [])
            ->willReturn(new CategoryAttributeStateDto(['description'], [$remoteValue]));

        $operations = $this->contributor($loader)->planNext(
            $desired,
            CategorySynchronizerInterface::MODE_RECONCILE
        );

        self::assertSame('categoryDeleteAttributeValueTranslations', $operations[0]->getField());
        self::assertSame(['en_GB'], $operations[0]->getVariables()['input']->getValue()['languages']);
    }

    private function contributor(CategoryAttributeStateLoader $loader): CategoryAttributeSynchronizationContributor
    {
        return new CategoryAttributeSynchronizationContributor($loader, new CategoryAttributeMutationFactory());
    }

    public function testBatchReadsAreIsolatedAndFreshForEveryPlanningRound(): void
    {
        $desired = new CategoryStateDto('chairs', [], [new CategoryAttributeStateDto(['description'])]);
        $loader = $this->createMock(CategoryAttributeStateLoader::class);
        $loader->expects(self::exactly(2))->method('loadBatch')->with(['chairs' => []])
            ->willReturnOnConsecutiveCalls(
                ['chairs' => new CategoryAttributeStateDto()],
                ['chairs' => new CategoryAttributeStateDto(['description'])]
            );
        $loader->expects(self::once())->method('load')->willReturn(new CategoryAttributeStateDto());
        $contributor = $this->contributor($loader);
        $first = $contributor->forBatch(['chairs' => $desired], CategorySynchronizerInterface::MODE_UPDATE);
        self::assertCount(1, $first->planNext($desired, CategorySynchronizerInterface::MODE_UPDATE));
        self::assertCount(1, $first->planNext($desired, CategorySynchronizerInterface::MODE_UPDATE));
        $second = $contributor->forBatch(['chairs' => $desired], CategorySynchronizerInterface::MODE_UPDATE);
        self::assertSame([], $second->planNext($desired, CategorySynchronizerInterface::MODE_UPDATE));
        self::assertCount(1, $contributor->planNext($desired, CategorySynchronizerInterface::MODE_UPDATE));
    }
}
