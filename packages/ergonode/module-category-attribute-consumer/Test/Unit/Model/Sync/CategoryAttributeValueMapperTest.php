<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\AttributeConsumer\Api\ErgonodeFileDownloaderInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\OptionLabelKeyNormalizer;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeValueMapper;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CategoryAttributeValueMapperTest extends TestCase
{
    public function testMapsTranslatedScalarsAndMappedOptionsToStoreValues(): void
    {
        $mappingProvider = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappingProvider->method('getValueMappings')->willReturn([
            [
                'mapping_id' => 1,
                'ergonode_attribute_code' => 'subtitle',
                'magento_attribute_code' => 'category_subtitle',
                'ergonode_type' => 'text',
                'magento_type' => 'text',
                'option_ids' => [],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
            ],
            [
                'mapping_id' => 2,
                'ergonode_attribute_code' => 'themes',
                'magento_attribute_code' => 'category_themes',
                'ergonode_type' => 'multi_select',
                'magento_type' => 'multiselect',
                'option_ids' => ['modern' => 31, 'classic' => 32],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
            ],
        ]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL', 2 => 'en_US']);
        $languages->method('getAdminLanguageCode')->willReturn('pl_PL');

        $result = $this->mapper($mappingProvider, $languages)->map([
            ['code' => 'subtitle', 'type' => 'text', 'values' => ['pl_PL' => 'Krzesła', 'en_US' => 'Chairs']],
            ['code' => 'themes', 'type' => 'multi_select', 'values' => [
                'pl_PL' => ['modern', 'classic'],
                'en_US' => ['classic'],
            ]],
        ]);

        self::assertSame([0 => 'Krzesła', 2 => 'Chairs'], $result['category_subtitle']);
        self::assertSame([0 => '31,32', 2 => '32'], $result['category_themes']);
    }

    public function testUsesOptionLabelsForTextTargetAndRejectsIncompleteOptionMapping(): void
    {
        $mappingProvider = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappingProvider->method('getValueMappings')->willReturn([
            [
                'mapping_id' => 3,
                'ergonode_attribute_code' => 'style',
                'magento_attribute_code' => 'category_style_label',
                'ergonode_type' => 'select',
                'magento_type' => 'text',
                'option_ids' => [],
                'option_labels' => ['modern' => ['pl_PL' => 'Nowoczesny']],
                'magento_option_ids_by_label' => [],
            ],
            [
                'mapping_id' => 4,
                'ergonode_attribute_code' => 'themes',
                'magento_attribute_code' => 'category_themes',
                'ergonode_type' => 'multiselect',
                'magento_type' => 'multiselect',
                'option_ids' => ['modern' => 31],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
            ],
        ]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $languages->method('getAdminLanguageCode')->willReturn('pl_PL');

        $result = $this->mapper($mappingProvider, $languages)->map([
            ['code' => 'style', 'type' => 'select', 'values' => ['pl_PL' => 'modern']],
            ['code' => 'themes', 'type' => 'multi_select', 'values' => ['pl_PL' => ['modern', 'missing']]],
        ]);

        self::assertSame([0 => 'Nowoczesny'], $result['category_style_label']);
        self::assertArrayNotHasKey('category_themes', $result);
    }

    public function testDownloadsImageToMagentoCategoryImageDirectory(): void
    {
        $mappingProvider = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappingProvider->method('getValueMappings')->willReturn([[
            'mapping_id' => 5,
            'ergonode_attribute_code' => 'hero',
            'magento_attribute_code' => 'image',
            'ergonode_type' => 'image',
            'magento_type' => 'image',
            'option_ids' => [],
            'option_labels' => [],
            'magento_option_ids_by_label' => [],
        ]]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $fileDownloader = $this->createMock(ErgonodeFileDownloaderInterface::class);
        $fileDownloader->expects($this->once())
            ->method('download')
            ->with('hero.jpg', 'catalog/category/ergonode')
            ->willReturn([
                'relative_path' => 'catalog/category/ergonode/h/e/hero.jpg',
                'media_url' => 'https://example.test/media/catalog/category/ergonode/h/e/hero.jpg',
                'source_url' => 'https://ergonode.test/hero.jpg',
                'downloaded' => true,
            ]);

        $result = (new CategoryAttributeValueMapper(
            $mappingProvider,
            $languages,
            $fileDownloader,
            new NullLogger(),
            $this->allowAllPolicy(),
            new ErgonodeAttributeTypeResolver(),
            new OptionLabelKeyNormalizer()
        ))->map([['code' => 'hero', 'type' => 'image', 'values' => ['pl_PL' => 'hero.jpg']]]);

        self::assertSame([0 => 'ergonode/h/e/hero.jpg'], $result['image']);
    }

    public function testIgnoresConfiguredManualMagentoAttributes(): void
    {
        $mappingProvider = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappingProvider->method('getValueMappings')->willReturn([[
            'mapping_id' => 6,
            'ergonode_attribute_code' => 'active',
            'magento_attribute_code' => 'is_active',
            'ergonode_type' => 'select',
            'magento_type' => 'boolean',
            'option_ids' => ['yes' => 1],
            'option_labels' => [],
            'magento_option_ids_by_label' => [],
        ]]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $policy = $this->createStub(CategoryAttributePolicy::class);
        $policy->method('isMappable')->willReturn(false);

        $result = (new CategoryAttributeValueMapper(
            $mappingProvider,
            $languages,
            $this->createStub(ErgonodeFileDownloaderInterface::class),
            new NullLogger(),
            $policy,
            new ErgonodeAttributeTypeResolver(),
            new OptionLabelKeyNormalizer()
        ))->map([['code' => 'active', 'type' => 'select', 'values' => ['pl_PL' => 'yes']]]);

        self::assertSame([], $result);
    }

    public function testSynchronizationClearsRemovedValuesWithoutClearingInvalidMappings(): void
    {
        $mappingProvider = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappingProvider->method('getValueMappings')->willReturn([
            [
                'ergonode_attribute_code' => 'subtitle',
                'magento_attribute_code' => 'category_subtitle',
                'ergonode_type' => 'text',
                'magento_type' => 'text',
                'option_ids' => [],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
            ],
            [
                'ergonode_attribute_code' => 'theme',
                'magento_attribute_code' => 'category_theme',
                'ergonode_type' => 'select',
                'magento_type' => 'select',
                'option_ids' => ['modern' => 31],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
            ],
        ]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL', 2 => 'en_US']);
        $languages->method('getAdminLanguageCode')->willReturn('pl_PL');

        $result = $this->mapper($mappingProvider, $languages)->mapForSynchronization([
            ['code' => 'theme', 'type' => 'select', 'values' => ['pl_PL' => 'unmapped-option']],
        ]);

        self::assertSame([], $result['values']);
        self::assertSame([0, 2], $result['clear']['category_subtitle']);
        self::assertSame([2], $result['clear']['category_theme']);
    }

    private function mapper(
        CategoryAttributeMappingProviderInterface $mappingProvider,
        LanguageStoreMappingProviderInterface $languages
    ): CategoryAttributeValueMapper {
        return new CategoryAttributeValueMapper(
            $mappingProvider,
            $languages,
            $this->createStub(ErgonodeFileDownloaderInterface::class),
            new NullLogger(),
            $this->allowAllPolicy(),
            new ErgonodeAttributeTypeResolver(),
            new OptionLabelKeyNormalizer()
        );
    }

    private function allowAllPolicy(): CategoryAttributePolicy
    {
        $policy = $this->createStub(CategoryAttributePolicy::class);
        $policy->method('isMappable')->willReturn(true);

        return $policy;
    }
}
