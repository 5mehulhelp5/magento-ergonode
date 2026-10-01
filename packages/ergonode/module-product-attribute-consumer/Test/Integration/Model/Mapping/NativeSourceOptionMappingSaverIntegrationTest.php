<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Mapping;

use Ergonode\ProductAttributeConsumer\Model\Mapping\OptionMappingSaver;
use Ergonode\ProductAttributeConsumer\Model\Product\ProductMappingProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class NativeSourceOptionMappingSaverIntegrationTest extends TestCase
{
    private const string ERGONODE_ATTRIBUTE_CODE = 'native_status_it';
    private const string ERGONODE_OPTION_CODE = 'enabled_it';

    #[Config('ergonode_products/attributes/status', 'mapping')]
    public function testPersistsAndExposesMappingToNativeStatusOption(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $attributeTable = $resource->getTableName('ergonode_attribute');
        $mappingTable = $resource->getTableName('ergonode_product_attribute_mapping');
        $optionMappingTable = $resource->getTableName('ergonode_product_option_mapping');

        $connection->delete($mappingTable, ['magento_attribute_code = ?' => 'status']);
        $connection->delete($attributeTable, ['code = ?' => self::ERGONODE_ATTRIBUTE_CODE]);
        $connection->insert($attributeTable, [
            'code' => self::ERGONODE_ATTRIBUTE_CODE,
            'type' => 'select',
            'scope' => 'global',
            'labels_json' => '{"en_US":"Native status"}',
            'parameters_json' => '{}',
            'content_hash' => hash('sha256', self::ERGONODE_ATTRIBUTE_CODE),
        ]);
        $connection->insert($mappingTable, [
            'ergonode_attribute_code' => self::ERGONODE_ATTRIBUTE_CODE,
            'magento_attribute_code' => 'status',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            'status' => 'complete',
            'content_hash' => hash('sha256', self::ERGONODE_ATTRIBUTE_CODE . ':status'),
        ]);
        $mappingId = (int)$connection->lastInsertId($mappingTable);

        $stats = $objectManager->get(OptionMappingSaver::class)->save($mappingId, [[
            'left' => ['code' => self::ERGONODE_OPTION_CODE],
            'right' => ['code' => 'option_1'],
        ]], []);

        self::assertSame(1, $stats['inserted']);
        $savedMapping = $connection->fetchRow(
            $connection->select()
                ->from($optionMappingTable, ['ergonode_option_code', 'magento_option_id', 'status'])
                ->where('attribute_mapping_id = ?', $mappingId)
        );
        self::assertIsArray($savedMapping);
        self::assertSame(self::ERGONODE_OPTION_CODE, $savedMapping['ergonode_option_code']);
        self::assertSame(1, (int)$savedMapping['magento_option_id']);
        self::assertSame('complete', $savedMapping['status']);

        $productMappings = $objectManager->create(ProductMappingProvider::class)->getMappings();
        self::assertSame(
            [self::ERGONODE_OPTION_CODE => 1],
            $productMappings[$mappingId]['option_ids']
        );
        self::assertTrue($productMappings[$mappingId]['magento_has_custom_source']);
    }
}
