<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Mapping;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Model\Data\MappingStateDto;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Exception\MappingConflictException;
use Magento\Framework\Lock\LockManagerInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Ergonode\Language\Model\Mapping\LanguageStoreMappingSaver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class LanguageStoreMappingSaverTest extends TestCase
{
    /** @param array<mixed> $mappings */
    #[DataProvider('invalidMappings')]
    public function testRejectsMalformedRowsBeforeAnyWrite(array $mappings): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        foreach (['beginTransaction', 'delete', 'update', 'insert'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid language mapping row.');
        $this->saver($connection)->save($mappings, [], 'revision');
    }

    /** @return array<string, array{array<mixed>}> */
    public static function invalidMappings(): array
    {
        return [
            'null row' => [[null]],
            'scalar row' => [['invalid']],
            'unknown fields' => [[['unexpected' => 'value']]],
            'scalar sides' => [[['left' => 'en_GB', 'right' => '0']]],
            'empty side' => [[['left' => [], 'right' => ['code' => '3']]]],
            'invalid language type' => [[['left' => ['code' => []], 'right' => null]]],
            'invalid store type' => [[['left' => null, 'right' => ['code' => false]]]],
            'empty row' => [[['left' => null, 'right' => null]]],
            'mixed valid and invalid' => [[['language_code' => 'en_GB', 'store_id' => 0], null]],
        ];
    }

    public function testRejectsExcludingDefaultValuesBeforeAnyWrite(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('beginTransaction');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Magento Default Values cannot be excluded from language mapping.');
        $this->saver($connection)->save([], [
            ['source' => 'magento', 'code' => '0', 'active' => false],
        ], 'revision');
    }

    public function testExplicitEmptyListStillRemovesMappings(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete')->with(
            'ergonode_language_store_mapping',
            ['mapping_id = ?' => 12]
        );
        $connection->expects(self::once())->method('commit');
        $result = $this->saver($connection, [[
            'mapping_id' => 12, 'store_id' => 3, 'language_code' => 'en_GB',
            'sort_order' => 0, 'is_manual' => 1,
        ]])->save([], [], 'revision');
        self::assertSame(1, $result['stats']['deleted']);
    }

    public function testPersistsManualMappingWhenLanguageAndStoreViewLocalesDiffer(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('insert')->with(
            'ergonode_language_store_mapping',
            [
                'store_id' => 3,
                'language_code' => 'en_GB',
                'sort_order' => 0,
                'is_manual' => 1,
            ]
        );
        $connection->expects(self::once())->method('commit');

        $stats = $this->saver($connection)->save([[
            'left' => ['code' => 'en_GB'],
            'right' => ['code' => '3'],
        ]], [], 'revision');

        self::assertSame(['inserted' => 1, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0], $stats['stats']);
    }

    public function testPersistsAdminScopeMapping(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('insert')->with(
            'ergonode_language_store_mapping',
            [
                'store_id' => 0,
                'language_code' => 'pl_PL',
                'sort_order' => 0,
                'is_manual' => 1,
            ]
        );
        $connection->expects(self::once())->method('commit');

        $this->saver($connection)->save([[
            'left' => ['code' => 'pl_PL'],
            'right' => ['code' => '0'],
        ]], [], 'revision');
    }

    public function testRejectsTwoLanguagesAssignedToTheSameStoreView(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('beginTransaction');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A Store View can be mapped to only one Ergonode language.');

        $this->saver($connection)->save([
            ['left' => ['code' => 'en_GB'], 'right' => ['code' => '3']],
            ['left' => ['code' => 'pl_PL'], 'right' => ['code' => '3']],
        ], [], 'revision');
    }

    public function testPersistsEveryUserMappingAsExplicit(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('insert')->with(
            'ergonode_language_store_mapping',
            [
                'store_id' => 3,
                'language_code' => 'pl_PL',
                'sort_order' => 0,
                'is_manual' => 1,
            ]
        );
        $connection->expects(self::once())->method('commit');

        $this->saver($connection)->save([[
            'left' => ['code' => 'pl_PL'],
            'right' => ['code' => '3'],
        ]], [], 'revision');
    }

    public function testPersistsStoreOnlyDraft(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('insert')->with(
            'ergonode_language_store_mapping',
            [
                'store_id' => 3,
                'language_code' => null,
                'sort_order' => 0,
                'is_manual' => 1,
            ]
        );
        $connection->expects(self::once())->method('commit');

        $stats = $this->saver($connection)->save([[
            'left' => null,
            'right' => ['code' => '3'],
        ]], [], 'revision');

        self::assertSame(['inserted' => 1, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0], $stats['stats']);
    }

    public function testReplacesCompleteMappingWithStoreOnlyDraft(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('delete')->with(
            'ergonode_language_store_mapping',
            ['mapping_id = ?' => 12]
        );
        $connection->expects(self::once())->method('insert')->with(
            'ergonode_language_store_mapping',
            [
                'store_id' => 3,
                'language_code' => null,
                'sort_order' => 0,
                'is_manual' => 1,
            ]
        );
        $connection->expects(self::once())->method('commit');

        $stats = $this->saver($connection, [[
            'mapping_id' => 12,
            'store_id' => 3,
            'language_code' => 'en_GB',
            'sort_order' => 0,
            'is_manual' => 1,
        ]])->save([[
            'left' => null,
            'right' => ['code' => '3'],
        ]], [], 'revision');

        self::assertSame(['inserted' => 1, 'updated' => 0, 'deleted' => 1, 'unchanged' => 0], $stats['stats']);
    }

    public function testRejectsStaleRevisionBeforeAnyWrite(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('beginTransaction');
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('insert');
        $this->expectException(MappingConflictException::class);
        $this->saver($connection)->save([], [], 'stale');
    }

    /** @param array<int, array<string, int|string|null>> $existingMappings */
    private function saver(AdapterInterface $connection, array $existingMappings = []): LanguageStoreMappingSaver
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturn('ergonode_language_store_mapping');
        $stateProvider = $this->createStub(LanguageMappingStateProviderInterface::class);
        $stateProvider->method('getState')->willReturn(new MappingStateDto(
            ['en_GB', 'pl_PL'],
            $existingMappings,
            [
                0 => [
                    'id' => 0, 'code' => 'admin', 'name' => 'Admin',
                    'locale' => 'pl_PL', 'website' => 'Global', 'group' => 'All',
                ],
                3 => [
                    'id' => 3, 'code' => 'french', 'name' => 'French',
                    'locale' => 'fr_FR', 'website' => 'Main', 'group' => 'Main',
                ],
            ],
            [],
            [],
            'revision'
        ));
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        return new LanguageStoreMappingSaver(
            $resourceConnection,
            $stateProvider,
            $this->createStub(MappingVisibilitySaverInterface::class),
            $this->createStub(LoggerInterface::class),
            new MappingLock($manager),
            $this->createStub(MappingCache::class)
        );
    }
}
