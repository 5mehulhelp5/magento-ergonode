<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoOptionLabelSyncer;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\ProductAttributeConsumer\Model\Sync\MagentoOptionSyncer;
use Ergonode\ProductAttributeConsumer\Model\Sync\MagentoOptionSyncResource;
use Ergonode\ProductAttributeConsumer\Model\Sync\OptionLabelResolver;
use Ergonode\ProductAttribute\Model\Sync\OptionMatchKeyResolver;
use Ergonode\ProductAttribute\Model\Sync\OptionPairPlanner;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean as BooleanSource;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MagentoOptionSyncerTest extends TestCase
{
    public function testPersistsClearPairsAndSkipsAmbiguousLanguageMatch(): void
    {
        $mapping = [
            'mapping_id' => 14, 'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color', 'ergonode_type' => 'select', 'magento_type' => 'select',
        ];
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getAttributeId')->willReturn(97);
        $attribute->method('getFrontendInput')->willReturn('select');
        $attribute->method('getSourceModel')->willReturn('');
        $repository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->willReturn($attribute);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('requireAdminLanguageCode')->willReturn('en_US');
        $languages->method('getLanguageStoreMap')->willReturn([2 => 'pl_PL']);
        $resource = $this->createMock(MagentoOptionSyncResource::class);
        $resource->method('loadErgonodeOptions')->willReturn([
            ['option_code' => 'blue', 'labels_json' => '{"en_US":"Blue"}', 'sort_order' => 1],
            ['option_code' => 'legacy', 'labels_json' => '{"pl_PL":"Czerwony"}', 'sort_order' => 2],
            ['option_code' => 'other', 'labels_json' => '{"pl_PL":"Same"}', 'sort_order' => 3],
        ]);
        $resource->method('loadMagentoOptions')->willReturn(['by_id' => [
            10 => ['option_id' => 10, 'sort_order' => 1, 'label' => 'Blue', 'store_labels' => []],
            20 => ['option_id' => 20, 'sort_order' => 2, 'label' => 'Unknown', 'store_labels' => [2 => 'Czerwony']],
            30 => ['option_id' => 30, 'sort_order' => 3, 'label' => 'A', 'store_labels' => [2 => 'Same']],
            31 => ['option_id' => 31, 'sort_order' => 4, 'label' => 'B', 'store_labels' => [2 => 'Same']],
        ]]);
        $resource->method('loadExistingMappings')->willReturn([]);
        $resource->expects(self::never())->method('createMagentoOption');
        $resource->expects(self::exactly(2))->method('syncMapping')->willReturn('inserted');
        $resource->expects(self::exactly(2))->method('syncSortOrder')->willReturn(false);
        $labelSyncer = $this->createMock(MagentoOptionLabelSyncer::class);
        $labelSyncer->expects(self::once())->method('syncLabelsBatch')
            ->with(97, [10 => ['en_US' => 'Blue'], 20 => ['pl_PL' => 'Czerwony']])
            ->willReturn([]);
        $stats = (new MagentoOptionSyncer(
            $repository,
            $this->createStub(MagentoOptionProvider::class),
            new OptionMatchKeyResolver(),
            $this->createStub(MappingVisibilityProviderInterface::class),
            $labelSyncer,
            new OptionLabelResolver(new Json(), $languages),
            $this->createStub(ChangeReport::class),
            $resource,
            new OptionPairPlanner(new OptionMatchKeyResolver())
        ))->syncAttributeMapping($mapping);

        self::assertSame(2, $stats['linked']);
        self::assertSame(1, $stats['skipped']);
        self::assertSame(0, $stats['created']);
    }

    public function testMatchesAndPersistsNativeBooleanOptionWithoutCreatingEavOption(): void
    {
        $mapping = [
            'mapping_id' => 12,
            'ergonode_attribute_code' => 'enabled',
            'magento_attribute_code' => 'is_enabled',
            'ergonode_type' => 'select',
            'magento_type' => 'boolean',
        ];
        $row = [
            'entity_id' => 5,
            'option_code' => 'enabled_yes',
            'labels_json' => '{"en_US":"Tak"}',
            'sort_order' => 1,
        ];
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getAttributeId')->willReturn(97);
        $attribute->method('getFrontendInput')->willReturn('boolean');
        $attribute->method('getSourceModel')->willReturn(BooleanSource::class);
        $attributeRepository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $attributeRepository->expects(self::once())->method('get')->with('is_enabled')->willReturn($attribute);
        $languageProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageProvider->method('requireAdminLanguageCode')->willReturn('en_US');
        $optionProvider = $this->createMock(MagentoOptionProvider::class);
        $optionProvider->expects(self::once())->method('getOptions')->with('is_enabled')->willReturn(
            [
            [
                'label' => 'No',
                'code' => 'option_0',
                'scope' => 'VALUE 0',
                'type' => 'option',
                'active' => true,
            ],
            [
                'label' => 'Yes',
                'code' => 'option_1',
                'scope' => 'VALUE 1',
                'type' => 'option',
                'active' => true,
            ],
            ]
        );
        $resource = $this->createMock(MagentoOptionSyncResource::class);
        $resource->expects(self::once())->method('loadErgonodeOptions')->with('enabled')->willReturn([$row]);
        $resource->expects(self::never())->method('syncSortOrder');
        $resource->expects(self::once())->method('loadExistingMappings')->with(12)->willReturn([]);
        $resource->expects(self::once())
            ->method('syncMapping')
            ->with(12, 'enabled_yes', 1, 1, self::isArray())
            ->willReturn('inserted');
        $labelSyncer = $this->createMock(MagentoOptionLabelSyncer::class);
        $labelSyncer->expects(self::never())->method('syncLabelsBatch');
        $stats = (new MagentoOptionSyncer(
            $attributeRepository,
            $optionProvider,
            new OptionMatchKeyResolver(),
            $this->createStub(MappingVisibilityProviderInterface::class),
            $labelSyncer,
            new OptionLabelResolver(new Json(), $languageProvider),
            $this->createStub(ChangeReport::class),
            $resource,
            new OptionPairPlanner(new OptionMatchKeyResolver())
        ))->syncAttributeMapping($mapping);

        self::assertSame(1, $stats['linked']);
        self::assertSame(1, $stats['mappings_inserted']);
        self::assertSame(0, $stats['created']);
        self::assertSame(0, $stats['skipped']);
    }

    #[DataProvider('sortOrderSynchronizationProvider')]
    public function testSynchronizesChangedRemotePositionOnlyWhenConfigured(
        bool $enabled,
        int $expectedUpdates
    ): void {
        $mapping = [
            'mapping_id' => 14,
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
        ];
        $row = [
            'entity_id' => 8,
            'option_code' => 'blue',
            'labels_json' => '{"en_US":"Blue"}',
            'sort_order' => 1,
        ];
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getAttributeId')->willReturn(97);
        $attribute->method('getFrontendInput')->willReturn('select');
        $attribute->method('getSourceModel')->willReturn('');
        $attributeRepository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $attributeRepository->expects(self::once())->method('get')->with('color')->willReturn($attribute);
        $languageProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageProvider->method('requireAdminLanguageCode')->willReturn('en_US');
        $visibility = $this->createStub(MappingVisibilityProviderInterface::class);
        $resource = $this->createMock(MagentoOptionSyncResource::class);
        $resource->expects(self::once())->method('loadErgonodeOptions')->with('color')->willReturn([$row]);
        $resource->expects(self::once())->method('loadMagentoOptions')->with(97)->willReturn(
            [
            'by_id' => [10 => ['option_id' => 10, 'sort_order' => 2, 'label' => 'Blue']],
            'by_label' => ['blue' => 10],
            ]
        );
        $resource->expects(self::once())->method('loadExistingMappings')->with(14)->willReturn(
            [
            'blue' => ['ergonode_option_code' => 'blue', 'magento_option_id' => 10],
            ]
        );
        $resource->expects(self::once())
            ->method('syncSortOrder')
            ->with(10, 1, self::isArray())
            ->willReturn($enabled);
        $resource->expects(self::once())->method('syncMapping')->willReturn('unchanged');
        $labelSyncer = $this->createMock(MagentoOptionLabelSyncer::class);
        $labelSyncer->expects(self::once())
            ->method('syncLabelsBatch')
            ->with(97, [10 => ['en_US' => 'Blue']])
            ->willReturn([10 => 0]);
        $stats = (new MagentoOptionSyncer(
            $attributeRepository,
            $this->createStub(MagentoOptionProvider::class),
            new OptionMatchKeyResolver(),
            $visibility,
            $labelSyncer,
            new OptionLabelResolver(new Json(), $languageProvider),
            $this->createStub(ChangeReport::class),
            $resource,
            new OptionPairPlanner(new OptionMatchKeyResolver())
        ))->syncAttributeMapping($mapping);

        self::assertSame($expectedUpdates, $stats['sort_order_updated']);
    }

    public function testCreatesMissingOptionThroughSharedMagentoCreatorAndMappingPersister(): void
    {
        $mapping = [
            'mapping_id' => 14,
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
        ];
        $row = [
            'entity_id' => 8,
            'option_code' => 'blue',
            'labels_json' => '{"en_US":"Blue"}',
            'sort_order' => 1,
        ];
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getAttributeId')->willReturn(97);
        $attribute->method('getFrontendInput')->willReturn('select');
        $attribute->method('getSourceModel')->willReturn('');
        $attributeRepository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $attributeRepository->method('get')->willReturn($attribute);
        $languageProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageProvider->method('requireAdminLanguageCode')->willReturn('en_US');
        $resource = $this->createMock(MagentoOptionSyncResource::class);
        $resource->expects(self::once())->method('loadErgonodeOptions')->with('color')->willReturn([$row]);
        $resource->expects(self::once())->method('loadMagentoOptions')->with(97)->willReturn(
            [
            'by_id' => [],
            'by_label' => [],
            ]
        );
        $resource->expects(self::once())->method('syncSortOrder')->willReturn(false);
        $resource->expects(self::once())->method('createMagentoOption')->with('color', 'Blue', 1)->willReturn(
            [
            'option_id' => 27,
            'label' => 'Blue',
            'code' => 'option_27',
            'scope' => 'ID 27',
            'type' => 'option',
            'source' => 'magento',
            'created' => true,
            ]
        );
        $resource->expects(self::once())->method('loadExistingMappings')->with(14)->willReturn([]);
        $resource->expects(self::once())
            ->method('syncMapping')
            ->with(14, 'blue', 27, 1, self::isArray())
            ->willReturn('inserted');
        $labelSyncer = $this->createMock(MagentoOptionLabelSyncer::class);
        $labelSyncer->expects(self::once())
            ->method('syncLabelsBatch')
            ->with(97, [27 => ['en_US' => 'Blue']])
            ->willReturn([27 => 1]);

        $stats = (new MagentoOptionSyncer(
            $attributeRepository,
            $this->createStub(MagentoOptionProvider::class),
            new OptionMatchKeyResolver(),
            $this->createStub(MappingVisibilityProviderInterface::class),
            $labelSyncer,
            new OptionLabelResolver(new Json(), $languageProvider),
            $this->createStub(ChangeReport::class),
            $resource,
            new OptionPairPlanner(new OptionMatchKeyResolver())
        ))->syncAttributeMapping($mapping);

        self::assertSame(1, $stats['created']);
        self::assertSame(1, $stats['linked']);
        self::assertSame(1, $stats['mappings_inserted']);
        self::assertSame(1, $stats['labels_updated']);
    }

    /**
     * @return array<string, array{bool, int}>
     */
    public static function sortOrderSynchronizationProvider(): array
    {
        return [
            'enabled' => [true, 1],
            'disabled' => [false, 0],
        ];
    }
}
