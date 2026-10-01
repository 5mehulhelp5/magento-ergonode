<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Integration\Model\ResourceModel;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\ProductCatalogInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Eav\Test\Fixture\AttributeSet as AttributeSetFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class ProductCatalogIntegrationTest extends TestCase
{
    #[DataFixture(AttributeSetFixture::class, [
        'entity_type_code' => 'catalog_product', 'skeleton_id' => 4, 'attribute_set_name' => 'Grid set',
    ], as: 'set')]
    #[DataFixture(AttributeSetFixture::class, [
        'entity_type_code' => 'catalog_product', 'skeleton_id' => 4, 'attribute_set_name' => 'Grid set extended',
    ], as: 'other_set')]
    #[DataFixture(ProductFixture::class, [
        'sku' => 'grid-options-simple', 'attribute_set_id' => '$set.attribute_set_id$',
    ], as: 'simple')]
    #[DataFixture(ProductFixture::class, [
        'sku' => 'grid-options-virtual', 'attribute_set_id' => '$set.attribute_set_id$', 'type_id' => 'virtual',
    ], as: 'virtual')]
    #[DataFixture(ProductFixture::class, [
        'sku' => 'grid-options-other', 'attribute_set_id' => '$other_set.attribute_set_id$', 'type_id' => 'virtual',
    ], as: 'other')]
    public function testSelectOptionsCoverTheCatalogAndExactFiltersAlsoApplyToSnapshots(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $simple = $fixtures->get('simple');
        $virtual = $fixtures->get('virtual');
        $other = $fixtures->get('other');
        self::assertInstanceOf(Product::class, $simple);
        self::assertInstanceOf(Product::class, $virtual);
        self::assertInstanceOf(Product::class, $other);
        $catalog = Bootstrap::getObjectManager()->get(ProductCatalogInterface::class);
        $page = $catalog->getPage('grid-options-simple', 1, 1);
        self::assertSame(1, $page['total']);
        $sets = array_column($page['filter_options']['attribute_set_id'], 'label', 'value');
        self::assertSame('Grid set', $sets[$simple->getAttributeSetId()]);
        self::assertSame('Grid set extended', $sets[$other->getAttributeSetId()]);
        self::assertContains(['value' => 'virtual', 'label' => 'virtual'], $page['filter_options']['type_id']);
        $criteria = ['attribute_set_id' => (string)$simple->getAttributeSetId(), 'type_id' => 'virtual'];
        $filtered = $catalog->getPage('grid-options-', 1, 20, $criteria);
        self::assertSame(1, $filtered['total']);
        self::assertSame((int)$virtual->getId(), $filtered['items'][0]['product_id']);
        self::assertSame([(int)$virtual->getId()], $catalog->getIds('grid-options-', [], [], true, $criteria));
        self::assertSame([], $catalog->getIds('grid-options-', [], [(int)$virtual->getId()], true, $criteria));
        self::assertSame($page['filter_options'], $filtered['filter_options']);
        $empty = $catalog->getPage('no-grid-options-product', 1, 20, $criteria);
        self::assertSame(0, $empty['total']);
        self::assertSame($page['filter_options'], $empty['filter_options']);
        self::assertSame(0, $catalog->getPage('grid-options-', 1, 20, ['type_id' => 'simp'])['total']);
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'publication-grid-a', 'name' => 'Publication grid A'], as: 'first')]
    #[DataFixture(ProductFixture::class, ['sku' => 'publication-grid-b', 'name' => 'Publication grid B'], as: 'second')]
    public function testPagesAndAllSelectionUseTheSameFilteredProductsAndMappedIdentity(): void
    {
        $first = DataFixtureStorageManager::getStorage()->get('first');
        $second = DataFixtureStorageManager::getStorage()->get('second');
        self::assertInstanceOf(Product::class, $first);
        self::assertInstanceOf(Product::class, $second);
        $firstId = (int)$first->getId();
        $secondId = (int)$second->getId();
        Bootstrap::getObjectManager()->get(ProductIdentityServiceInterface::class)->bind(
            $secondId,
            '10000002',
            'assigned'
        );
        $catalog = Bootstrap::getObjectManager()->get(ProductCatalogInterface::class);
        $page = $catalog->getPage('publication-grid-', 2, 1);
        self::assertSame(2, $page['total']);
        self::assertSame($secondId, $page['items'][0]['product_id']);
        self::assertSame('10000002', $page['items'][0]['ergonode_sku']);
        self::assertSame([$firstId, $secondId], $catalog->getIds('publication-grid-', [], [], true));
        self::assertSame([$firstId], $catalog->getIds('publication-grid-', [], [$secondId], true));
        self::assertSame([], $catalog->getIds('', [], [], false));
        self::assertSame([$secondId], $catalog->getIds('', [$secondId], [], false));
        self::assertSame(1, $catalog->getPage('Publication grid B', 1, 20)['total']);
        self::assertNotEmpty($page['items'][0]['attribute_set_name']);
        self::assertNotSame((string)$second->getAttributeSetId(), $page['items'][0]['attribute_set_name']);
        $criteria = ['ergonode_sku' => '10000002', 'attribute_set_name' => $page['items'][0]['attribute_set_name']];
        $filtered = $catalog->getPage('publication-grid-', 99, 1, $criteria);
        self::assertSame(1, $filtered['total']);
        self::assertSame(1, $filtered['page']);
        self::assertSame($secondId, $filtered['items'][0]['product_id']);
        self::assertSame([$secondId], $catalog->getIds('publication-grid-', [], [], true, $criteria));
        self::assertSame([], $catalog->getIds('publication-grid-', [], [$secondId], true, $criteria));
        foreach (['sku', 'name', 'product_id', 'ergonode_sku'] as $sort) {
            $sorted = $catalog->getPage('publication-grid-', 1, 1, ['sort' => $sort, 'direction' => 'DESC']);
            self::assertSame($secondId, $sorted['items'][0]['product_id'], $sort);
        }
        self::assertSame(1, $catalog->getPage('', 1, 20, ['product_id' => (string)$firstId])['total']);
        self::assertSame(0, $catalog->getPage('publication-grid-', 1, 20, ['type_id' => 'virtual'])['total']);
    }
}
