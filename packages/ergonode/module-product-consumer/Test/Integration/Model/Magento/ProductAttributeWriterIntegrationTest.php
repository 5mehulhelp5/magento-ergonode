<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Integration\Model\Magento;

use Ergonode\ProductConsumer\Model\Magento\ProductAttributeWriter;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Test\Fixture\Group as GroupFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\Store\Test\Fixture\Website as WebsiteFixture;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// Store fixtures create index tables; their DDL cannot run inside the test transaction.
#[
    AppIsolation(true),
    DbIsolation(false),
    DataFixture(WebsiteFixture::class, as: 'website_a'),
    DataFixture(GroupFixture::class, ['website_id' => '$website_a.id$'], as: 'group_a'),
    DataFixture(StoreFixture::class, [
        'website_id' => '$website_a.id$', 'store_group_id' => '$group_a.id$'
    ], as: 'store_a'),
    DataFixture(StoreFixture::class, [
        'website_id' => '$website_a.id$', 'store_group_id' => '$group_a.id$'
    ], as: 'store_a2'),
    DataFixture(WebsiteFixture::class, as: 'website_b'),
    DataFixture(GroupFixture::class, ['website_id' => '$website_b.id$'], as: 'group_b'),
    DataFixture(StoreFixture::class, [
        'website_id' => '$website_b.id$', 'store_group_id' => '$group_b.id$'
    ], as: 'store_b'),
    DataFixture(AttributeFixture::class, [
        'attribute_code' => 'pc_ws_%uniqid%', 'scope' => 'website'
    ], as: 'website_attribute'),
    DataFixture(AttributeFixture::class, [
        'attribute_code' => 'pc_gl_%uniqid%', 'scope' => 'global'
    ], as: 'global_attribute'),
    DataFixture(AttributeFixture::class, [
        'attribute_code' => 'pc_st_%uniqid%', 'scope' => 'store'
    ], as: 'store_attribute'),
    DataFixture(ProductFixture::class, ['sku' => 'pc-writer-%uniqid%'], as: 'product')
]
class ProductAttributeWriterIntegrationTest extends TestCase
{
    public function testWebsiteValuesRemainDistinctAndClearOnlyTheSelectedWebsite(): void
    {
        $writer = Bootstrap::getObjectManager()->get(ProductAttributeWriter::class);
        $productId = (int)$this->fixture('product')->getId();
        $websiteCode = (string)$this->fixture('website_attribute')->getAttributeCode();
        $globalCode = (string)$this->fixture('global_attribute')->getAttributeCode();
        $storeCode = (string)$this->fixture('store_attribute')->getAttributeCode();
        $storeA = (int)$this->fixture('store_a')->getId();
        $storeA2 = (int)$this->fixture('store_a2')->getId();
        $storeB = (int)$this->fixture('store_b')->getId();

        $writer->write($productId, [
            $websiteCode => [0 => 'default', $storeA => 'website-a', $storeB => 'website-b'],
            $globalCode => [0 => 'global', $storeA => 'ignored'],
            $storeCode => [0 => 'store-default', $storeA => 'store-a'],
        ]);

        self::assertSame(
            [0 => 'default', $storeA => 'website-a', $storeA2 => 'website-a',
            $storeB => 'website-b'],
            $this->storedValues($productId, $websiteCode)
        );
        self::assertSame([0 => 'global'], $this->storedValues($productId, $globalCode));
        self::assertSame(
            [0 => 'store-default', $storeA => 'store-a'],
            $this->storedValues($productId, $storeCode)
        );

        $writer->write(
            $productId,
            [$websiteCode => [$storeB => 'website-b-updated']],
            [$websiteCode => [$storeA]]
        );

        self::assertSame(
            [0 => 'default', $storeB => 'website-b-updated'],
            $this->storedValues($productId, $websiteCode)
        );
        self::assertSame([0 => 'global'], $this->storedValues($productId, $globalCode));
        self::assertSame(
            [0 => 'store-default', $storeA => 'store-a'],
            $this->storedValues($productId, $storeCode)
        );
    }

    public function testConflictingStoreValuesWithinOneWebsiteDoNotWrite(): void
    {
        $writer = Bootstrap::getObjectManager()->get(ProductAttributeWriter::class);
        $productId = (int)$this->fixture('product')->getId();
        $websiteCode = (string)$this->fixture('website_attribute')->getAttributeCode();
        $storeA = (int)$this->fixture('store_a')->getId();
        $storeA2 = (int)$this->fixture('store_a2')->getId();

        try {
            $writer->write($productId, [$websiteCode => [$storeA => 'first', $storeA2 => 'second']]);
            self::fail('Conflicting website values must be rejected.');
        } catch (LocalizedException) {
            self::assertSame([], $this->storedValues($productId, $websiteCode));
        }
    }

    private function fixture(string $name): DataObject
    {
        return DataFixtureStorageManager::getStorage()->get($name);
    }

    /** @return array<int, string> */
    private function storedValues(int $productId, string $attributeCode): array
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->get(ResourceConnection::class);
        $productResource = $manager->get(ProductResource::class);
        $attribute = $manager->get(EavConfig::class)->getAttribute(Product::ENTITY, $attributeCode);
        $connection = $resource->getConnection();
        $linkField = $productResource->getLinkField();
        $linkValue = $linkField === $productResource->getIdFieldName() ? $productId : $connection->fetchOne(
            $connection->select()->from($productResource->getEntityTable(), [$linkField])
                ->where($productResource->getIdFieldName() . ' = ?', $productId)
        );

        return $connection->fetchPairs(
            $connection->select()->from(
                $resource->getTableName((string)$attribute->getBackendTable()),
                ['store_id', 'value']
            )
                ->where('attribute_id = ?', (int)$attribute->getAttributeId())
                ->where($linkField . ' = ?', $linkValue)
                ->order('store_id ASC')
        );
    }
}
