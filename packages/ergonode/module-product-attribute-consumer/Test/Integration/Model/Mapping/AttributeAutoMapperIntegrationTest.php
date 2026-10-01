<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Mapping;

use Ergonode\ProductAttributeConsumer\Api\AttributeAutoMapperInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class AttributeAutoMapperIntegrationTest extends TestCase
{
    private const string COMPATIBLE_CODE = 'ergonode_auto_map_it';
    private const string INCOMPATIBLE_CODE = 'ergonode_auto_conflict_it';
    private const string MISSING_CODE = 'ergonode_auto_create_it';

    #[Config('ergonode_attributes/mapping/map_identical_codes', '1')]
    public function testPersistsCompatibleCodeOnceAndReportsTypeConflict(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $dataSetup = $objectManager->get(ModuleDataSetupInterface::class);
        $eavSetup = $objectManager->get(EavSetupFactory::class)->create(['setup' => $dataSetup]);

        foreach ([self::COMPATIBLE_CODE, self::INCOMPATIBLE_CODE, self::MISSING_CODE] as $code) {
            $eavSetup->removeAttribute(Product::ENTITY, $code);
            $connection->delete(
                $resource->getTableName('ergonode_product_attribute_mapping'),
                ['ergonode_attribute_code = ?' => $code]
            );
            $connection->delete(
                $resource->getTableName('ergonode_attribute'),
                ['code = ?' => $code]
            );
        }

        try {
            $this->addMagentoAttribute($eavSetup, self::COMPATIBLE_CODE, 'text');
            $this->addMagentoAttribute($eavSetup, self::INCOMPATIBLE_CODE, 'text');
            $this->addErgonodeAttribute($resource, self::COMPATIBLE_CODE, 'text');
            $this->addErgonodeAttribute($resource, self::INCOMPATIBLE_CODE, 'numeric');
            $this->addErgonodeAttribute($resource, self::MISSING_CODE, 'text');

            $mapper = $objectManager->get(AttributeAutoMapperInterface::class);
            $first = $mapper->synchronize();
            $second = $mapper->synchronize();

            self::assertSame(2, $first['inserted']);
            self::assertSame(1, $first['created']);
            self::assertSame(1, $first['conflicts']);
            self::assertSame(0, $second['inserted']);
            self::assertSame(0, $second['created']);
            self::assertSame(1, $this->mappingCount($resource, self::COMPATIBLE_CODE));
            self::assertSame(0, $this->mappingCount($resource, self::INCOMPATIBLE_CODE));
            self::assertSame(1, $this->mappingCount($resource, self::MISSING_CODE));
            self::assertSame(
                self::MISSING_CODE,
                $objectManager->get(ProductAttributeRepositoryInterface::class)
                    ->get(self::MISSING_CODE)
                    ->getAttributeCode()
            );
        } finally {
            foreach ([self::COMPATIBLE_CODE, self::INCOMPATIBLE_CODE, self::MISSING_CODE] as $code) {
                $connection->delete(
                    $resource->getTableName('ergonode_product_attribute_mapping'),
                    ['ergonode_attribute_code = ?' => $code]
                );
                $connection->delete(
                    $resource->getTableName('ergonode_attribute'),
                    ['code = ?' => $code]
                );
                $eavSetup->removeAttribute(Product::ENTITY, $code);
            }
        }
    }

    private function addMagentoAttribute(EavSetup $eavSetup, string $code, string $input): void
    {
        $eavSetup->addAttribute(Product::ENTITY, $code, [
            'type' => 'varchar',
            'input' => $input,
            'label' => $code,
            'required' => false,
            'user_defined' => true,
        ]);
    }

    private function addErgonodeAttribute(ResourceConnection $resource, string $code, string $type): void
    {
        $resource->getConnection()->insert($resource->getTableName('ergonode_attribute'), [
            'code' => $code,
            'type' => $type,
            'scope' => 'global',
            'labels_json' => sprintf('{"en_US":"%s"}', $code),
            'parameters_json' => '{}',
            'content_hash' => hash('sha256', $code . ':' . $type),
        ]);
    }

    private function mappingCount(ResourceConnection $resource, string $code): int
    {
        return (int)$resource->getConnection()->fetchOne(
            $resource->getConnection()->select()
                ->from($resource->getTableName('ergonode_product_attribute_mapping'), ['COUNT(*)'])
                ->where('ergonode_attribute_code = ?', $code)
        );
    }
}
