<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Integration\Model\Import;

use Ergonode\TemplateConsumer\Model\Import\TemplateSnapshotReconciler;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class TemplateSnapshotReconcilerIntegrationTest extends TestCase
{
    public function testCompletedListSoftDeletesOnlyMissingActiveTemplates(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insertMultiple($resource->getTableName('ergonode_template'), [
            $this->templateRow('active'),
            $this->templateRow('missing'),
            $this->templateRow('already_deleted', true),
        ]);

        self::assertSame(
            1,
            Bootstrap::getObjectManager()->get(TemplateSnapshotReconciler::class)->reconcile(['active'])
        );
        self::assertSame(
            ['active' => '0', 'already_deleted' => '1', 'missing' => '1'],
            $connection->fetchPairs(
                $connection->select()
                    ->from($resource->getTableName('ergonode_template'), ['code', 'is_deleted'])
                    ->where('code IN (?)', ['active', 'already_deleted', 'missing'])
                    ->order('code ASC')
            )
        );
    }

    /** @return array<string, int|string> */
    private function templateRow(string $code, bool $isDeleted = false): array
    {
        return [
            'code' => $code,
            'is_deleted' => $isDeleted ? 1 : 0,
            'content_hash' => hash('sha256', $code),
            'raw_json' => '{}',
        ];
    }
}
