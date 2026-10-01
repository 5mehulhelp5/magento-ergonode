<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Setup;

use Ergonode\ProductAttributeHistory\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testMissingTablesRequireNoDeletion(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('endSetup');
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('dropTable');
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }
}
