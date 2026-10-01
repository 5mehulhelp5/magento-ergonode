<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Snapshot;

use Ergonode\TemplateConsumer\Model\Snapshot\TemplateSnapshotRemover;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class TemplateSnapshotRemoverTest extends TestCase
{
    public function testAtomicallyRemovesTemplateSnapshot(): void
    {
        $select = $this->select();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchRow')->with($select)->willReturn([
            'entity_id' => '7',
        ]);
        $deletedTables = [];
        $connection->expects(self::once())->method('delete')->willReturnCallback(
            static function (string $table, array $where) use (&$deletedTables): int {
                $deletedTables[] = [$table, $where];

                return 1;
            }
        );
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $this->remover($connection)->remove(' product_default ');

        self::assertSame([
            ['ergonode_template', ['entity_id = ?' => 7]],
        ], $deletedTables);
    }

    private function select(): Select
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('forUpdate')->willReturnSelf();

        return $select;
    }

    private function remover(AdapterInterface $connection): TemplateSnapshotRemover
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new TemplateSnapshotRemover($resource);
    }
}
