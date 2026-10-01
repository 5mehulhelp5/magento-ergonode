<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Integration\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use Ergonode\ProductAttribute\Model\ResourceModel\CompleteMappingProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class ValueAdapterMappingIntegrationTest extends TestCase
{
    public function testMissingAdapterSurvivesSaveAndIsExcludedFromBothDirections(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_product_attribute_mapping');
        $connection->delete($table);
        $connection->insert($table, [
            'ergonode_attribute_code' => 'remote_reference',
            'magento_attribute_code' => 'reference',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'status' => 'complete',
            'value_adapter' => 'removed_adapter',
            'content_hash' => hash('sha256', 'initial'),
            'sort_order' => 0,
        ]);
        $objects->get(AttributeMappingSaver::class)->save([[
            'left' => ['code' => 'remote_reference', 'type' => 'text'],
            'right' => ['code' => 'reference', 'type' => 'text'],
            'value_adapter' => null,
        ]], []);
        self::assertSame(
            'removed_adapter',
            $connection->fetchOne($connection->select()->from($table, 'value_adapter'))
        );
        $provider = $objects->create(CompleteMappingProvider::class, ['valueAdapters' => new ValueAdapterRegistry()]);
        self::assertCount(1, $provider->getMappings());
        self::assertSame([], $provider->getMappings('import'));
        self::assertSame([], $provider->getMappings('publish'));
    }
}
