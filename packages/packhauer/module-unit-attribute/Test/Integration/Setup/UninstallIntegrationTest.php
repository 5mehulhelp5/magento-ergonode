<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Test\Integration\Setup;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PackHauer\UnitAttribute\Model\Attribute\Backend\Unit;
use PackHauer\UnitAttribute\Setup\Uninstall;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class UninstallIntegrationTest extends TestCase
{
    private const string ATTRIBUTE_CODE = 'packhauer_unit_uninstall_it';
    private const string LEGACY_ATTRIBUTE_CODE = 'packhauer_unit_legacy_it';
    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'packhauer-unit-uninstall-%uniqid%'], as: 'product')]
    public function testRemovesAttributesMetadataOptionsValuesAndBackendModels(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $dataSetup = $objectManager->get(ModuleDataSetupInterface::class);
        $eavSetup = $objectManager->get(EavSetupFactory::class)->create(['setup' => $dataSetup]);
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();

        $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        $eavSetup->removeAttribute(Product::ENTITY, self::LEGACY_ATTRIBUTE_CODE);

        try {
            $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
                'type' => 'decimal',
                'input' => 'unit',
                'label' => 'Unit uninstall integration',
                'required' => false,
                'user_defined' => true,
                'backend' => Unit::class,
                'additional_data' => '{"vendivo_unit":{"name":"CENTIMETER","symbol":"cm"}}',
            ]);
            $eavSetup->addAttribute(Product::ENTITY, self::LEGACY_ATTRIBUTE_CODE, [
                'type' => 'decimal',
                'input' => 'text',
                'label' => 'Legacy unit uninstall integration',
                'required' => false,
                'user_defined' => true,
                'backend' => $this->legacyBackendModel(),
            ]);

            $attributeId = $this->getAttributeId($resource, self::ATTRIBUTE_CODE);
            $legacyAttributeId = $this->getAttributeId($resource, self::LEGACY_ATTRIBUTE_CODE);
            $product = DataFixtureStorageManager::getStorage()->get('product');
            self::assertInstanceOf(Product::class, $product);
            $productResource = $objectManager->get(ProductResource::class);
            $linkField = $productResource->getLinkField();
            $linkValue = (int)$connection->fetchOne(
                $connection->select()
                    ->from($resource->getTableName('catalog_product_entity'), [$linkField])
                    ->where($productResource->getIdFieldName() . ' = ?', (int)$product->getId())
                    ->limit(1)
            );
            self::assertGreaterThan(0, $linkValue);
            foreach ([$attributeId => '12.5', $legacyAttributeId => '2.5'] as $id => $value) {
                $connection->insert($resource->getTableName('catalog_product_entity_decimal'), [
                    'attribute_id' => $id,
                    'store_id' => 0,
                    $linkField => $linkValue,
                    'value' => $value,
                ]);
                self::assertSame(1, $this->countById(
                    $resource,
                    'catalog_product_entity_decimal',
                    'attribute_id',
                    $id
                ));
            }
            $connection->insert($resource->getTableName('eav_attribute_option'), [
                'attribute_id' => $attributeId,
                'sort_order' => 0,
            ]);
            $optionId = (int)$connection->lastInsertId($resource->getTableName('eav_attribute_option'));
            $connection->insert($resource->getTableName('eav_attribute_option_value'), [
                'option_id' => $optionId,
                'store_id' => 0,
                'value' => 'Centimeter',
            ]);

            $setup = $this->createStub(SchemaSetupInterface::class);
            $setup->method('getConnection')->willReturn($connection);
            $setup->method('getTable')->willReturnCallback(
                static fn(string $table): string => $resource->getTableName($table)
            );
            $objectManager->create(Uninstall::class)->uninstall(
                $setup,
                $this->createStub(ModuleContextInterface::class)
            );

            self::assertSame(0, $this->getAttributeId($resource, self::ATTRIBUTE_CODE));
            self::assertSame(0, $this->getAttributeId($resource, self::LEGACY_ATTRIBUTE_CODE));
            self::assertSame(0, $this->countById($resource, 'catalog_eav_attribute', 'attribute_id', $attributeId));
            self::assertSame(0, $this->countById($resource, 'eav_attribute_option', 'option_id', $optionId));
            self::assertSame(0, $this->countById($resource, 'eav_attribute_option_value', 'option_id', $optionId));
            self::assertSame(0, $this->countById(
                $resource,
                'catalog_product_entity_decimal',
                'attribute_id',
                $attributeId
            ));
            self::assertSame(0, $this->countById(
                $resource,
                'catalog_product_entity_decimal',
                'attribute_id',
                $legacyAttributeId
            ));
        } finally {
            $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
            $eavSetup->removeAttribute(Product::ENTITY, self::LEGACY_ATTRIBUTE_CODE);
        }
    }

    private function getAttributeId(ResourceConnection $resource, string $attributeCode): int
    {
        $connection = $resource->getConnection();
        $attributeId = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $attributeCode)
                ->limit(1)
        );

        return $attributeId === false ? 0 : (int)$attributeId;
    }

    private function countById(
        ResourceConnection $resource,
        string $table,
        string $idField,
        int $id
    ): int {
        $connection = $resource->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName($table), ['count' => 'COUNT(*)'])
                ->where($idField . ' = ?', $id)
        );
    }

    private function legacyBackendModel(): string
    {
        return implode('\\', ['Vendivo', 'UnitAttribute', 'Model', 'Attribute', 'Backend', 'Unit']);
    }
}
