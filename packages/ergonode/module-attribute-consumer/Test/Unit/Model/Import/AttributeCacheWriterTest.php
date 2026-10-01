<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AttributeCacheWriterTest extends TestCase
{
    public function testSaveAttributesLoadsHashesOnceAndPersistsOnlyChanges(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('from')
            ->with('ergonode_attribute', ['code', 'content_hash'])
            ->willReturnSelf();
        $select->expects($this->once())
            ->method('where')
            ->with('code IN (?)', ['same', 'changed', 'new'])
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('select')->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn([
                ['code' => 'same', 'content_hash' => 'hash-1'],
                ['code' => 'changed', 'content_hash' => 'old-hash'],
            ]);
        $connection->expects($this->once())
            ->method('insertOnDuplicate')
            ->with(
                'ergonode_attribute',
                self::callback(static fn (array $rows): bool => array_column($rows, 'code') === ['changed', 'new']
                    && !array_key_exists('metadata_json', $rows[0])
                    && !array_key_exists('raw_json', $rows[0])),
                [
                    'type',
                    'scope',
                    'labels_json',
                    'parameters_json',
                    'content_hash',
                    'synced_at',
                ]
            );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->with('ergonode_attribute')
            ->willReturn('ergonode_attribute');
        $writer = new AttributeCacheWriter(
            $resource,
            new Json(),
            new OptionSnapshotCache(),
            $this->createStub(ErgonodeAttributeProvider::class)
        );

        $results = $writer->saveAttributes([
            $this->attribute('same', 'hash-1'),
            $this->attribute('changed', 'hash-2'),
            $this->attribute('new', 'hash-3'),
        ]);

        self::assertSame([
            'same' => 'unchanged',
            'changed' => 'updated',
            'new' => 'inserted',
        ], $results);
    }

    public function testSaveAttributesDoesNotWriteWhenAllHashesMatch(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([
            ['code' => 'same', 'content_hash' => 'hash-1'],
        ]);
        $connection->expects(self::never())->method('insertOnDuplicate');

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('ergonode_attribute');

        $provider = $this->createMock(ErgonodeAttributeProvider::class);
        $provider->expects(self::never())->method('reset');

        self::assertSame(
            ['same' => 'unchanged'],
            (new AttributeCacheWriter(
                $resource,
                new Json(),
                new OptionSnapshotCache(),
                $provider
            ))->saveAttributes([
                $this->attribute('same', 'hash-1'),
            ])
        );
    }

    public function testFailedWriteDoesNotInvalidatePreviouslyReadDefinitions(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);
        $connection->expects(self::once())->method('insertOnDuplicate')
            ->willThrowException(new RuntimeException('write failed'));
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('ergonode_attribute');
        $provider = $this->createMock(ErgonodeAttributeProvider::class);
        $provider->expects(self::never())->method('reset');
        $writer = new AttributeCacheWriter($resource, new Json(), new OptionSnapshotCache(), $provider);
        self::assertSame([], $writer->saveAttributes([]));
        $this->expectExceptionMessage('write failed');
        $writer->saveAttributes([$this->attribute('new', 'hash')]);
    }

    public function testSaveOptionsPersistsOnlyChangedAndNewRows(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([
            ['option_code' => 'same', 'content_hash' => 'hash-1'],
            ['option_code' => 'changed', 'content_hash' => 'old-hash'],
        ]);
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                'ergonode_attribute_option',
                self::callback(static function (array $rows): bool {
                    self::assertSame(['changed', 'new'], array_column($rows, 'option_code'));
                    return !array_key_exists('raw_json', $rows[0]);
                }),
                ['sort_order', 'labels_json', 'content_hash', 'synced_at']
            );

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('ergonode_attribute_option');

        $results = (new AttributeCacheWriter(
            $resource,
            new Json(),
            new OptionSnapshotCache(),
            $this->createStub(ErgonodeAttributeProvider::class)
        ))->saveOptions('color', [
            $this->option('same', 'hash-1'),
            $this->option('changed', 'hash-2'),
            $this->option('new', 'hash-3'),
        ]);

        self::assertSame([
            'same' => 'unchanged',
            'changed' => 'updated',
            'new' => 'inserted',
        ], $results);
    }

    /**
     * @return array{
     *     code: string,
     *     type: string,
     *     scope: string,
     *     labels: array<string, string>,
     *     parameters: array<string, bool|string>,
     *     hash: string
     * }
     */
    private function attribute(string $code, string $hash): array
    {
        return [
            'code' => $code,
            'type' => 'TEXT',
            'scope' => 'global',
            'labels' => ['pl_PL' => $code],
            'parameters' => [],
            'hash' => $hash,
        ];
    }

    /** @return array{code: string, labels: array<string, string>, sort_order: int, hash: string} */
    private function option(string $code, string $hash): array
    {
        return [
            'code' => $code,
            'labels' => ['pl_PL' => $code],
            'sort_order' => 1,
            'hash' => $hash,
        ];
    }
}
