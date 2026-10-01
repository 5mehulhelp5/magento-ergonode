<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Setup;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOwnedAndLegacyRuntimeDataWithoutBroadMatching(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $deletes = [];
        $connection->expects(self::exactly(2))->method('delete')->willReturnCallback(
            static function (string $table, array $condition) use (&$deletes): int {
                $deletes[$table] = $condition;

                return 1;
            }
        );
        $droppedTables = [];
        $connection->expects(self::exactly(2))->method('dropTable')->willReturnCallback(
            static function (string $table) use (&$droppedTables): void {
                $droppedTables[] = $table;
            }
        );

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        $configurationPaths = $deletes['prefix_core_config_data']['path IN (?)'];
        self::assertCount(12, $configurationPaths);
        self::assertNotContains('ergonode_category_attributes/cron/schedule', $configurationPaths);
        self::assertContains(
            CategoryAttributeConfigProvider::LEGACY_XML_PATH_SYNCHRONIZATION_ENABLED,
            $configurationPaths
        );
        self::assertArrayNotHasKey('prefix_cron_schedule', $deletes);
        self::assertSame(
            ['process_code IN (?)' => ['category_deleted_stream']],
            $deletes['prefix_ergonode_import_cursor']
        );
        self::assertArrayNotHasKey('prefix_ergonode_mapping_visibility', $deletes);
        self::assertSame([
            'prefix_ergonode_category_entity_snapshot',
            'prefix_ergonode_category_attribute',
        ], $droppedTables);
    }
}
