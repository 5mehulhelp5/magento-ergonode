<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Snapshot;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeSnapshotRemoverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class CategoryAttributeSnapshotRemoverIntegrationTest extends TestCase
{
    public function testRemovalPreservesGlobalAttributeCacheAndCategoryMapping(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $code = 'snapshot_category_attribute';
        $connection->insert($resource->getTableName('ergonode_attribute'), [
            'code' => $code,
            'type' => 'text',
            'scope' => 'global',
            'labels_json' => '{"en_US":"Snapshot category attribute"}',
            'parameters_json' => '{}',
            'content_hash' => hash('sha256', $code),
        ]);
        $connection->insert(
            $resource->getTableName('ergonode_category_attribute'),
            ['attribute_code' => $code]
        );
        $connection->insert($resource->getTableName('ergonode_category_attribute_mapping'), [
            'ergonode_attribute_code' => $code,
            'magento_attribute_code' => null,
            'ergonode_type' => 'text',
            'magento_type' => null,
            'status' => 'partial',
            'content_hash' => hash('sha256', $code . ':mapping'),
        ]);

        $objectManager->get(CategoryAttributeSnapshotRemoverInterface::class)->remove($code);

        self::assertSame(
            0,
            $this->countRows($resource, 'ergonode_category_attribute', 'attribute_code', $code)
        );
        self::assertSame(1, $this->countRows($resource, 'ergonode_attribute', 'code', $code));
        self::assertSame(
            1,
            $this->countRows(
                $resource,
                'ergonode_category_attribute_mapping',
                'ergonode_attribute_code',
                $code
            )
        );
    }

    private function countRows(
        ResourceConnection $resource,
        string $table,
        string $column,
        string $value
    ): int {
        $connection = $resource->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()->from($resource->getTableName($table), ['COUNT(*)'])
                ->where($column . ' = ?', $value)
        );
    }
}
