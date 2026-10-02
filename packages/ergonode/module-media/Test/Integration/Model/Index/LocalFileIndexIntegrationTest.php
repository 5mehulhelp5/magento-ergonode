<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Integration\Model\Index;

use Ergonode\Media\Model\ResourceModel\LocalFileIndex;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class LocalFileIndexIntegrationTest extends TestCase
{
    public function testBinaryHashesAndCaseSensitivePathsSurviveUpsertPaginationAndRemoval(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $table = 'tmp_erg_media_index_' . bin2hex(random_bytes(8));
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn($table);
        $definition = $connection->newTable($table)
            ->addColumn('path_hash', Table::TYPE_VARBINARY, 32, ['primary' => true, 'nullable' => false])
            ->addColumn('path', Table::TYPE_TEXT, 1024, ['nullable' => false])
            ->addColumn('content_hash', Table::TYPE_VARBINARY, 32, ['nullable' => false])
            ->addColumn('size', Table::TYPE_BIGINT, null, ['unsigned' => true, 'nullable' => false])
            ->addColumn('modified_at', Table::TYPE_BIGINT, null, ['unsigned' => true, 'nullable' => false]);
        $connection->createTable($definition);
        try {
            $index = new LocalFileIndex($resource);
            $hash = hash('sha256', 'same contents', true);
            $upper = 'catalog/product/A.jpg';
            $lower = 'catalog/product/a.jpg';
            $index->save($upper, $hash, 12, 123);
            $index->save($lower, $hash, 12, 123);
            self::assertCount(2, $index->find($hash));
            self::assertSame($hash, $index->get($upper)['content_hash']);
            self::assertSame($upper, $index->page('', 1)[0]['path']);
            self::assertSame($lower, $index->page($upper, 1)[0]['path']);
            self::assertSame([], $index->page($lower, 1));
            $changed = hash('sha256', 'changed', true);
            $index->save($upper, $changed, 7, 124);
            self::assertSame(7, $index->get($upper)['size']);
            self::assertCount(1, $index->find($hash));
            $index->remove($upper);
            self::assertNull($index->get($upper));
            self::assertSame($lower, $index->get($lower)['path']);
            self::assertSame('catalog/product/a.jpg', $connection->fetchOne(
                $connection->select()->from($table, ['path'])
            ));
        } finally {
            $connection->dropTable($table);
        }
    }
}
