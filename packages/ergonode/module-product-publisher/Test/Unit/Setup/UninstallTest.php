<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Setup;

use Ergonode\ProductPublisher\Setup\Uninstall;
use Ergonode\ProductPublisher\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOnlyOwnedAuthorizationRulesAndPreservesConfiguration(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $deletes = [
            ['prefix_authorization_rule', ['resource_id LIKE ?' => 'Ergonode_ProductPublisher::%']],
        ];
        $connection->expects(self::exactly(count($deletes)))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $where) use (&$deletes): int {
                self::assertSame(array_shift($deletes), [$table, $where]);

                return 1;
            });

        $cleaner = $this->createMock(RuntimeRecordCleaner::class);
        $cleaner->expects(self::once())->method('execute')->with($setup);

        (new Uninstall($cleaner))->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame([], $deletes);
    }

    public function testToleratesMissingTables(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');

        $cleaner = $this->createMock(RuntimeRecordCleaner::class);
        $cleaner->expects(self::once())->method('execute')->with($setup);

        (new Uninstall($cleaner))->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }
}
