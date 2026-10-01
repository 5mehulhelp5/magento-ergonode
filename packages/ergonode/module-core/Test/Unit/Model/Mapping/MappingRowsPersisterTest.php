<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Mapping;

use Ergonode\Core\Model\Mapping\MappingRowsPersister;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class MappingRowsPersisterTest extends TestCase
{
    public function testPersistsOnlyChangedRowsAndReturnsOperationCounts(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('delete')
            ->with('mapping_table', ['mapping_id = ?' => 1]);
        $connection->expects(self::once())
            ->method('insert')
            ->with('mapping_table', ['content_hash' => 'new', 'sort_order' => 3]);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'mapping_table',
                ['content_hash' => 'changed', 'sort_order' => 2],
                ['mapping_id = ?' => 3]
            );

        $persister = new MappingRowsPersister(new Json());
        $stats = $persister->persist(
            $connection,
            'mapping_table',
            [
                'removed' => ['mapping_id' => 1, 'content_hash' => 'removed', 'sort_order' => 0],
                'same' => ['mapping_id' => 2, 'content_hash' => 'same', 'sort_order' => 1],
                'changed' => ['mapping_id' => 3, 'content_hash' => 'old', 'sort_order' => 2],
            ],
            [
                'same' => ['content_hash' => 'same', 'sort_order' => 1],
                'changed' => ['content_hash' => 'changed', 'sort_order' => 2],
                'new' => ['content_hash' => 'new', 'sort_order' => 3],
            ]
        );

        self::assertSame([
            'inserted' => 1,
            'updated' => 1,
            'deleted' => 1,
            'unchanged' => 1,
        ], $stats);
        self::assertSame(
            hash('sha256', '{"left":"color","right":"42"}'),
            $persister->hash(['right' => '42', 'left' => 'color'])
        );
    }
}
