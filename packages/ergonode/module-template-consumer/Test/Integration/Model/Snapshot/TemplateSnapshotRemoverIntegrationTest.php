<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Integration\Model\Snapshot;

use Ergonode\TemplateConsumer\Api\TemplateSnapshotRemoverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class TemplateSnapshotRemoverIntegrationTest extends TestCase
{
    public function testRemovalDeletesCompleteUnmappedTemplateCache(): void
    {
        $resource = $this->createTemplate('snapshot_template');
        $objectManager = Bootstrap::getObjectManager();

        $objectManager->get(TemplateSnapshotRemoverInterface::class)->remove('snapshot_template');

        self::assertSame(0, $this->countRows($resource, 'ergonode_template', 'code', 'snapshot_template'));
    }

    private function createTemplate(string $code): ResourceConnection
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert($resource->getTableName('ergonode_template'), [
            'code' => $code,
            'content_hash' => hash('sha256', $code),
            'raw_json' => '{}',
        ]);
        return $resource;
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
