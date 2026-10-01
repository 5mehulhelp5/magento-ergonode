<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Unit\Setup;

use Ergonode\CategoryConsumerHistory\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOnlyHistoryStorageAndAcl(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects(self::once())->method('delete')->with(
            'prefix_authorization_rule',
            ['resource_id IN (?)' => ['Ergonode_CategoryConsumerHistory::view']]
        );
        $droppedTables = [];
        $connection->expects(self::exactly(3))->method('dropTable')->willReturnCallback(
            static function (string $table) use (&$droppedTables): void {
                $droppedTables[] = $table;
            }
        );

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame([
            'prefix_ergonode_category_history_change',
            'prefix_ergonode_category_history_change_set',
            'prefix_ergonode_category_history_operation',
        ], $droppedTables);
    }
}
