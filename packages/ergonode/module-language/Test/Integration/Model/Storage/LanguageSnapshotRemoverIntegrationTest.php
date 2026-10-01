<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Integration\Model\Storage;

use Ergonode\Language\Api\LanguageSnapshotRemoverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class LanguageSnapshotRemoverIntegrationTest extends TestCase
{
    public function testRemovalPreservesStoreMapping(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $languageTable = $resource->getTableName('ergonode_language');
        $mappingTable = $resource->getTableName('ergonode_language_store_mapping');
        $connection->insert($languageTable, ['language_code' => 'snapshot_test']);
        $connection->insert($mappingTable, [
            'store_id' => null,
            'language_code' => 'snapshot_test',
            'sort_order' => 91,
            'is_manual' => 1,
        ]);

        $objectManager->get(LanguageSnapshotRemoverInterface::class)->remove('snapshot_test');

        self::assertSame(0, (int)$connection->fetchOne(
            $connection->select()->from($languageTable, ['COUNT(*)'])
                ->where('language_code = ?', 'snapshot_test')
        ));
        self::assertSame(1, (int)$connection->fetchOne(
            $connection->select()->from($mappingTable, ['COUNT(*)'])
                ->where('language_code = ?', 'snapshot_test')
        ));
    }
}
