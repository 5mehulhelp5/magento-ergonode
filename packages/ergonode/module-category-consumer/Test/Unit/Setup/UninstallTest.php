<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Setup;

use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\CategoryConsumer\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOnlyOwnedRuntimeRecords(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $deletes = [];
        $connection->expects(self::exactly(4))->method('delete')->willReturnCallback(
            static function (string $table, array $condition) use (&$deletes): int {
                $deletes[$table] = $condition;

                return 1;
            }
        );

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame(
            ['job_code IN (?)' => ['ergonode_category_import', 'ergonode_category_attribute_import']],
            $deletes['prefix_cron_schedule']
        );
        self::assertSame(
            ['process_code IN (?)' => [
                CategoryTreeStreamImporter::PROCESS_CODE, 'category_stream', 'category_tree_deleted_stream',
            ]],
            $deletes['prefix_ergonode_import_cursor']
        );
        self::assertArrayNotHasKey('prefix_ergonode_mapping_visibility', $deletes);
        self::assertContains('ergonode_categories/cron/status', $deletes['prefix_core_config_data']['path IN (?)']);
        self::assertContains(
            'ergonode_category_attributes/cron/status',
            $deletes['prefix_core_config_data']['path IN (?)']
        );
        self::assertSame(
            ['Ergonode_CategoryConsumer::category_tree_sync'],
            $deletes['prefix_authorization_rule']['resource_id IN (?)']
        );
    }
}
