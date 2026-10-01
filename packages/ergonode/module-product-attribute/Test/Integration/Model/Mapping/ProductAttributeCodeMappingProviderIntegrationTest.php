<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Integration\Model\Mapping;

use Ergonode\ProductAttribute\Api\ProductAttributeCodeMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class ProductAttributeCodeMappingProviderIntegrationTest extends TestCase
{
    public function testReturnsOnlyCompleteRequestedMappings(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_product_attribute_mapping');
        $connection->insertMultiple($table, [
            [
                'ergonode_attribute_code' => 'remote_color',
                'magento_attribute_code' => 'color',
                'status' => 'complete',
                'content_hash' => hash('sha256', 'remote_color'),
                'sort_order' => 10,
            ],
            [
                'ergonode_attribute_code' => 'remote_size',
                'magento_attribute_code' => 'size',
                'status' => 'pending',
                'content_hash' => hash('sha256', 'remote_size'),
                'sort_order' => 20,
            ],
        ]);

        $provider = Bootstrap::getObjectManager()->get(
            ProductAttributeCodeMappingProviderInterface::class
        );

        self::assertSame(
            ['remote_color' => 'color'],
            $provider->getMagentoAttributeCodes([
                ' remote_color ',
                'remote_color',
                'remote_size',
                'missing',
            ])
        );
    }
}
