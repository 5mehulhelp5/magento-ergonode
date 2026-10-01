<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Integration\Model\ResourceModel;

use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\AttributeConsumer\Model\Snapshot\AttributeSnapshotRemover;
use Ergonode\AttributeConsumer\Model\ResourceModel\AttributeDefinitionSnapshot;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class AttributeDefinitionSnapshotTest extends TestCase
{
    public function testDefinitionReadsFollowWritesReplacementAndRemovalOnSharedProvider(): void
    {
        $objects = Bootstrap::getObjectManager();
        $snapshot = $objects->get(AttributeDefinitionSnapshot::class);
        $state = ['source' => 'provider-cache-test', 'attribute' => 'v1', 'deleted' => null];
        $snapshot->replace([$this->definition('alpha')], $state);
        $provider = $objects->get(ErgonodeAttributeProvider::class);
        self::assertSame('alpha', $provider->getAttribute('alpha')['label']);
        self::assertSame([], $provider->getRelationAttributes());
        $writer = $objects->get(AttributeCacheWriter::class);
        $writer->saveAttributes([
            array_replace($this->definition('alpha'), ['labels' => ['en_US' => 'Updated'], 'hash' => 'updated']),
            $this->definition('beta'),
            array_replace($this->definition('related'), ['type' => 'relation']),
        ]);
        self::assertSame('Updated', $provider->getAttribute('alpha')['label']);
        self::assertSame(['alpha', 'beta'], array_keys($provider->getAttributeMap()));
        self::assertSame(['related'], array_column($provider->getRelationAttributes(), 'code'));
        $objects->get(AttributeSnapshotRemover::class)->remove('beta');
        self::assertNull($provider->getAttribute('beta'));
        $snapshot->replace([], array_replace($state, ['attribute' => 'v2']));
        self::assertSame([], $provider->getAttributeMap());
        self::assertSame([], $provider->getRelationAttributes());
    }

    public function testReplacingSnapshotPagesLocallyAndInvalidatesOptions(): void
    {
        $objects = Bootstrap::getObjectManager();
        $snapshot = $objects->get(AttributeDefinitionSnapshot::class);
        $state = ['source' => 'snapshot-test', 'attribute' => 'v1', 'deleted' => null];
        $snapshot->replace([$this->definition('alpha'), $this->definition('beta')], $state);
        $writer = $objects->get(AttributeCacheWriter::class);
        $provider = $objects->get(ErgonodeOptionProviderInterface::class);
        self::assertSame([], $provider->getOptionDefinitions('alpha'));
        $writer->saveOptions('alpha', [[
            'code' => '123', 'labels' => ['en_US' => 'One'], 'sort_order' => 0, 'hash' => hash('sha256', 'one'),
        ]]);
        self::assertSame('123', $provider->getOptionDefinitions('alpha')[0]['code']);
        $first = $snapshot->page(null, 1);
        self::assertSame(['alpha'], $first['attribute_codes']);
        self::assertTrue($first['has_more']);
        $second = $snapshot->page($first['cursor'], 1);
        self::assertSame(['beta'], $second['attribute_codes']);
        self::assertFalse($second['has_more']);
        self::assertNull($second['cursor']);
        $removed = $snapshot->replace([$this->definition('beta')], array_replace($state, ['attribute' => 'v2']));
        self::assertSame(['alpha'], $removed);
        self::assertSame([], $provider->getOptionDefinitions('alpha'));
        $this->expectException(LocalizedException::class);
        $snapshot->page($first['cursor'], 1);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testFailureAfterFlushingBatchRollsBackRowsAndCheckpoint(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $backup = [];
        foreach (['ergonode_attribute', 'ergonode_attribute_option', 'ergonode_import_cursor'] as $name) {
            $table = $resource->getTableName($name);
            $backup[$table] = $connection->fetchAll($connection->select()->from($table));
        }
        // An outer test transaction would defer the real rollback until the test ends.
        try {
            $snapshot = Bootstrap::getObjectManager()->get(AttributeDefinitionSnapshot::class);
            $state = ['source' => 'snapshot-test', 'attribute' => 'v1', 'deleted' => null];
            $snapshot->replace([$this->definition('original')], $state);
            $provider = Bootstrap::getObjectManager()->get(ErgonodeAttributeProvider::class);
            self::assertSame(['original'], array_keys($provider->getAttributeMap()));
            $definitions = function (): iterable {
                for ($index = 0; $index < 201; ++$index) {
                    yield $this->definition('new_' . $index);
                }
                throw new RuntimeException('Failed after the first database batch.');
            };
            try {
                $snapshot->replace($definitions(), array_replace($state, ['attribute' => 'v2']));
                self::fail('Expected the replacement to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame('Failed after the first database batch.', $exception->getMessage());
            }
            self::assertSame(['original'], $snapshot->getCodes());
            self::assertSame(['original'], array_keys($provider->getAttributeMap()));
            self::assertSame($state, $snapshot->getState());
        } finally {
            foreach (array_reverse(array_keys($backup)) as $table) {
                $connection->delete($table);
            }
            foreach ($backup as $table => $rows) {
                if ($rows !== []) {
                    $connection->insertMultiple($table, $rows);
                }
            }
        }
    }

    /**
     * @return array{code: string, type: string, scope: string, labels: array<string, string>,
     *     parameters: array<string, bool|string>, hash: string}
     */
    private function definition(string $code): array
    {
        return [
            'code' => $code, 'type' => 'select', 'scope' => 'GLOBAL',
            'labels' => ['en_US' => $code], 'parameters' => [], 'hash' => hash('sha256', $code),
        ];
    }
}
