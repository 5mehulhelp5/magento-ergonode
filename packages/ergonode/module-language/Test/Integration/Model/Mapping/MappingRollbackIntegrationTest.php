<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Integration\Model\Mapping;

use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Model\Mapping\LanguageStoreMappingSaver;
use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Model\Mapping\MappingLock;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

// Rollback must own the outer transaction so restored rows are immediately observable.
#[AppIsolation(true), DbIsolation(false)]
class MappingRollbackIntegrationTest extends TestCase
{
    private const string OLD_CODE = 'language_rollback_old';
    private const string NEW_CODE = 'language_rollback_new';

    #[DataProvider('failureStages')]
    public function testFailedSaveRestoresMappingsVisibilityAndRevision(bool $failDuringMapping): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $languageTable = $resource->getTableName('ergonode_language');
        $mappingTable = $resource->getTableName('ergonode_language_store_mapping');
        $visibilityTable = $resource->getTableName('ergonode_mapping_visibility');
        $stateProvider = $manager->get(LanguageMappingStateProviderInterface::class);
        $visibilitySaver = $manager->get(MappingVisibilitySaverInterface::class);
        $before = $stateProvider->getState();
        self::assertNotContains(self::OLD_CODE, $before->codes);
        self::assertNotContains(self::NEW_CODE, $before->codes);

        try {
            $connection->insertArray($languageTable, ['language_code'], [[self::OLD_CODE], [self::NEW_CODE]]);
            $connection->insert($mappingTable, [
                'language_code' => self::OLD_CODE, 'store_id' => null, 'sort_order' => 900, 'is_manual' => 1,
            ]);
            $visibilitySaver->saveMany([[
                'entity_type' => 'language', 'source' => 'ergo', 'identifier' => self::OLD_CODE, 'active' => true,
            ]]);
            $before = $stateProvider->getState();
            $visibilityBefore = $connection->fetchAll(
                $connection->select()->from($visibilityTable)->order('entity_id')
            );
            $mappings = array_values(array_filter(
                $before->rows,
                static fn (array $row): bool => $row['language_code'] !== self::OLD_CODE
            ));
            $mappings[] = ['language_code' => self::NEW_CODE, 'store_id' => null];
            $failure = new RuntimeException('Injected persistence failure.');
            $adapter = $this->createMock(AdapterInterface::class);
            foreach (['beginTransaction', 'delete', 'update', 'rollBack'] as $method) {
                $adapter->method($method)->willReturnCallback($connection->$method(...));
            }
            $adapter->expects(self::never())->method('commit');
            $adapter->expects(self::once())->method('insert')->willReturnCallback(
                static function (string $table, array $row) use ($connection, $failDuringMapping, $failure): int {
                    if ($failDuringMapping) {
                        // Existing rows have already been deleted/updated when this insert fails.
                        throw $failure;
                    }
                    return $connection->insert($table, $row);
                }
            );
            $failingResource = $this->createStub(ResourceConnection::class);
            $failingResource->method('getConnection')->willReturn($adapter);
            $failingResource->method('getTableName')->willReturnCallback($resource->getTableName(...));
            $visibility = $this->createMock(MappingVisibilitySaverInterface::class);
            if ($failDuringMapping) {
                $visibility->expects(self::never())->method('saveMany');
            } else {
                $visibility->expects(self::once())->method('saveMany')->willReturnCallback(
                    static function (array $items) use ($visibilitySaver, $failure): void {
                        $visibilitySaver->saveMany($items);
                        throw $failure;
                    }
                );
            }
            $cache = $this->createMock(MappingCache::class);
            $cache->expects(self::never())->method('invalidate');
            $saver = new LanguageStoreMappingSaver(
                $failingResource,
                $stateProvider,
                $visibility,
                new NullLogger(),
                $manager->get(MappingLock::class),
                $cache
            );

            try {
                $saver->save($mappings, [
                    ['source' => 'ergo', 'code' => self::OLD_CODE, 'active' => false],
                    ['source' => 'ergo', 'code' => self::NEW_CODE, 'active' => false],
                ], $before->revision);
                self::fail('The injected persistence failure was ignored.');
            } catch (LocalizedException $exception) {
                self::assertSame('Unable to save language mappings.', $exception->getMessage());
            }

            self::assertSame($before->rows, $stateProvider->getState()->rows);
            self::assertSame($before->revision, $stateProvider->getState()->revision);
            self::assertSame(
                $visibilityBefore,
                $connection->fetchAll($connection->select()->from($visibilityTable)->order('entity_id'))
            );
        } finally {
            $connection->delete($mappingTable, ['language_code IN (?)' => [self::OLD_CODE, self::NEW_CODE]]);
            $connection->delete($visibilityTable, [
                'entity_type = ?' => 'language', 'source = ?' => 'ergo',
                'identifier IN (?)' => [self::OLD_CODE, self::NEW_CODE],
            ]);
            $connection->delete($languageTable, ['language_code IN (?)' => [self::OLD_CODE, self::NEW_CODE]]);
            $manager->get(MappingCache::class)->invalidate();
        }
    }

    /** @return array<string, array{bool}> */
    public static function failureStages(): array
    {
        return ['mapping insert failure' => [true], 'failure after visibility writes' => [false]];
    }
}
