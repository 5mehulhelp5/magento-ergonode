<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\OptionMappingPersister;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OptionMappingPersisterTest extends TestCase
{
    #[DataProvider('logicalKeyProvider')]
    public function testBuildsCanonicalLogicalKey(string $ergonodeCode, ?int $magentoId, string $expected): void
    {
        $persister = new OptionMappingPersister(
            $this->createStub(ResourceConnection::class),
            $this->createStub(MappingRowsPersisterInterface::class)
        );

        self::assertSame($expected, $persister->logicalKey($ergonodeCode, $magentoId));
    }

    /**
     * @return array<string, array{string, int|null, string}>
     */
    public static function logicalKeyProvider(): array
    {
        return [
            'complete mapping' => ['blue', 27, 'full:blue|27'],
            'Ergonode draft' => ['blue', null, 'ergo:blue'],
            'Magento draft' => ['', 27, 'magento:27'],
        ];
    }

    public function testAutomaticUpsertUsesTheSameMappingHashContractAsManualSave(): void
    {
        $payload = [
            'attribute_mapping_id' => 12,
            'ergonode_option_code' => 'blue',
            'magento_option_id' => 27,
            'status' => 'complete',
        ];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('insert')->with(
            'ergonode_product_option_mapping',
            $payload + ['content_hash' => 'shared-hash', 'sort_order' => 3]
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $rowsPersister = $this->createMock(MappingRowsPersisterInterface::class);
        $rowsPersister->expects(self::once())->method('hash')->with($payload)->willReturn('shared-hash');
        $existing = [];

        $result = (new OptionMappingPersister(
            $resource,
            $rowsPersister
        ))->upsertComplete(
            12,
            'blue',
            27,
            3,
            $existing
        );

        self::assertSame('inserted', $result);
        self::assertSame('shared-hash', $existing['blue']['content_hash']);
    }

    public function testSnapshotReconciliationDeletesMappingsThroughSharedPersister(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);
        $connection->expects(self::once())->method('delete')->with(
            'ergonode_product_option_mapping',
            [
                'attribute_mapping_id IN (?)' => [12],
                'ergonode_option_code IN (?)' => ['blue'],
            ]
        )->willReturn(1);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $deleted = (new OptionMappingPersister(
            $resource,
            $this->createStub(MappingRowsPersisterInterface::class)
        ))->deleteByOptionCodes([12], ['blue']);

        self::assertSame(1, $deleted);
    }

    public function testAttributeMappingRemovalDeletesOptionMappingsThroughSharedPersister(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);
        $connection->expects(self::once())->method('delete')->with(
            'ergonode_product_option_mapping',
            ['attribute_mapping_id IN (?)' => [12]]
        )->willReturn(2);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $deleted = (new OptionMappingPersister(
            $resource,
            $this->createStub(MappingRowsPersisterInterface::class)
        ))->deleteByAttributeMappingIds([12]);

        self::assertSame(2, $deleted);
    }
}
