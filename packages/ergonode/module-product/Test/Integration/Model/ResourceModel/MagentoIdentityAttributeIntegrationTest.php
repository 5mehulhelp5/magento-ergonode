<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Integration\Model\ResourceModel;

use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class MagentoIdentityAttributeIntegrationTest extends TestCase
{
    #[Config('ergonode_products/identity/magento_attribute', 'navireo_id')]
    #[DataFixture(ProductFixture::class, ['sku' => 'magento-navireo-one'], as: 'first')]
    #[DataFixture(ProductFixture::class, ['sku' => 'magento-navireo-two'], as: 'second')]
    public function testGlobalEavAttributeIsWrittenFoundAndProtectedFromRebinding(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $setup = $objectManager->get(EavSetupFactory::class)->create([
            'setup' => $objectManager->get(ModuleDataSetupInterface::class),
        ]);
        $setup->addAttribute(Product::ENTITY, 'navireo_id', [
            'type' => 'varchar',
            'input' => 'text',
            'label' => 'Navireo ID',
            'global' => 1,
            'unique' => true,
            'required' => false,
            'user_defined' => true,
        ]);
        $objectManager->get(EavConfig::class)->clear();
        $first = DataFixtureStorageManager::getStorage()->get('first');
        $second = DataFixtureStorageManager::getStorage()->get('second');
        self::assertInstanceOf(Product::class, $first);
        self::assertInstanceOf(Product::class, $second);
        $attribute = $objectManager->get(MagentoIdentityAttributeInterface::class);

        $attribute->validate();
        self::assertSame('Navireo ID', $attribute->getEligibleAttributes()['navireo_id']);
        $attribute->write((int)$first->getId(), 'NAV-001');
        self::assertSame(
            [(int)$first->getId() => 'NAV-001', (int)$second->getId() => ''],
            $attribute->getValuesByProductIds([(int)$first->getId(), (int)$second->getId()])
        );
        self::assertSame((int)$first->getId(), $attribute->findProductId('NAV-001'));
        self::assertNull($attribute->findProductId('NAV-002'));
        self::assertSame(['NAV-001' => (int)$first->getId()], $attribute->findProductIds([
            'NAV-001', 'NAV-002', 'NAV-001', ''
        ]));
        $attribute->write((int)$first->getId(), 'NAV-001');

        $this->expectException(LocalizedException::class);
        $attribute->write((int)$second->getId(), 'NAV-001');
    }

    #[Config('ergonode_products/identity/magento_attribute', 'external_identity')]
    #[DataFixture(ProductFixture::class, ['sku' => 'duplicate-owner-one'], as: 'first')]
    #[DataFixture(ProductFixture::class, ['sku' => 'duplicate-owner-two'], as: 'second')]
    public function testDuplicateStoredIdentityValueIsRejected(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $setup = $objectManager->get(EavSetupFactory::class)->create([
            'setup' => $objectManager->get(ModuleDataSetupInterface::class),
        ]);
        $setup->addAttribute(Product::ENTITY, 'external_identity', [
            'type' => 'varchar',
            'input' => 'text',
            'label' => 'External identity',
            'global' => 1,
            'unique' => true,
            'required' => false,
            'user_defined' => true,
        ]);
        $eavConfig = $objectManager->get(EavConfig::class);
        $eavConfig->clear();
        $first = DataFixtureStorageManager::getStorage()->get('first');
        $second = DataFixtureStorageManager::getStorage()->get('second');
        self::assertInstanceOf(Product::class, $first);
        self::assertInstanceOf(Product::class, $second);
        $identityAttribute = $objectManager->get(MagentoIdentityAttributeInterface::class);
        $identityAttribute->write((int)$first->getId(), 'DUPLICATE');
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $productTable = $resource->getTableName('catalog_product_entity');
        $linkField = $objectManager->get(ProductResource::class)->getLinkField();
        $linkValue = $connection->fetchOne($connection->select()->from($productTable, [$linkField])
            ->where('entity_id = ?', (int)$second->getId()));
        $attribute = $eavConfig->getAttribute(Product::ENTITY, 'external_identity');
        $connection->insert($resource->getTableName($attribute->getBackendTable()), [
            'attribute_id' => (int)$attribute->getAttributeId(),
            'store_id' => 0,
            $linkField => $linkValue,
            'value' => 'DUPLICATE',
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('more than one Magento product');
        $identityAttribute->findProductIds(['DUPLICATE']);
    }

    #[Config('ergonode_products/identity/magento_attribute', 'sku')]
    #[DataFixture(ProductFixture::class, ['sku' => 'static-sku-source'], as: 'product')]
    public function testExistingStaticSkuCanBeReadByTheSameAttributeCodeContract(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(Product::class, $product);
        $attribute = Bootstrap::getObjectManager()->get(MagentoIdentityAttributeInterface::class);

        $attribute->validate();
        self::assertArrayHasKey('sku', $attribute->getEligibleAttributes());
        self::assertSame(
            [(int)$product->getId() => 'static-sku-source'],
            $attribute->getValuesByProductIds([(int)$product->getId()])
        );
        self::assertSame((int)$product->getId(), $attribute->findProductId('static-sku-source'));
        self::assertSame(
            ['static-sku-source' => (int)$product->getId()],
            $attribute->findProductIds(['static-sku-source', 'missing'])
        );
    }
}
