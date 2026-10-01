<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Integration;

use Ergonode\CategoryAttributePublisher\Api\MagentoOptionLabelReaderInterface;
use Ergonode\CategoryAttributePublisher\Model\Provider\CategoryAttributeSourceStateBuilder;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Language\Model\LocalizedStoreProjection;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class CategoryAttributeOptionLabelsIntegrationTest extends TestCase
{
    private const string ATTRIBUTE_CODE = 'ergonode_category_labels_it';
    private const string OTHER_CODE = 'ergonode_category_other_it';

    public function testBuildsTranslatedOptionsFromPersistedEavLabels(): void
    {
        $objects = Bootstrap::getObjectManager();
        $setup = $objects->get(CategorySetupFactory::class)->create([
            'setup' => $objects->get(ModuleDataSetupInterface::class),
        ]);
        $eav = $objects->get(Config::class);
        $storeId = (int)$objects->get(StoreManagerInterface::class)->getDefaultStoreView()->getId();
        try {
            foreach ([self::ATTRIBUTE_CODE, self::OTHER_CODE] as $code) {
                $setup->addAttribute(Category::ENTITY, $code, [
                    'type' => 'varchar', 'input' => 'select', 'label' => 'Color',
                    'required' => false, 'user_defined' => true, 'global' => 0,
                ]);
            }
            $attributeId = (int)$setup->getAttributeId(Category::ENTITY, self::ATTRIBUTE_CODE);
            $setup->addAttributeOption([
                'attribute_id' => $attributeId,
                'value' => ['red' => [0 => 'Red', $storeId => 'Czerwony'], 'blue' => [0 => 'Blue']],
            ]);
            $setup->addAttributeOption([
                'attribute_id' => (int)$setup->getAttributeId(Category::ENTITY, self::OTHER_CODE),
                'value' => ['other' => [0 => 'Other']],
            ]);
            $eav->clear();
            $labels = $objects->get(MagentoOptionLabelReaderInterface::class)->read($attributeId);
            self::assertCount(2, $labels);
            $redId = array_search('Red', array_map(static fn (array $row): string => $row[0], $labels), true);
            self::assertNotFalse($redId);
            $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
            $languages->method('getLanguageStoreMap')->willReturn([0 => 'en_US', $storeId => 'pl_PL']);
            $builder = $objects->create(CategoryAttributeSourceStateBuilder::class, [
                'localizedProjection' => new LocalizedStoreProjection($languages),
            ]);

            foreach (['select', 'multiselect'] as $type) {
                $state = $builder->build(self::ATTRIBUTE_CODE, $type);
                $names = [];
                foreach ($state->getOptions() as $option) {
                    $names[$option->getCode()] = $option->getNames();
                }
                self::assertSame('LOCAL', $state->getScope());
                self::assertCount(2, $names);
                self::assertSame(['en_US' => 'Red', 'pl_PL' => 'Czerwony'], $names['option_' . $redId]);
                self::assertContains(['en_US' => 'Blue', 'pl_PL' => 'Blue'], $names);
            }
        } finally {
            $setup->removeAttribute(Category::ENTITY, self::ATTRIBUTE_CODE);
            $setup->removeAttribute(Category::ENTITY, self::OTHER_CODE);
            $eav->clear();
        }
    }
}
