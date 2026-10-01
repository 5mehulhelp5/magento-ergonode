<?php

declare(strict_types=1);

namespace Ergonode\Consumer\Test\Unit\Setup;

use Ergonode\Consumer\Model\Config\ConnectionMode;
use Ergonode\Consumer\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOnlyOwnedWriteConfiguration(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('core_config_data')->willReturn('prefix_core_config_data');
        $connection->method('isTableExists')->with('prefix_core_config_data')->willReturn(true);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('delete')->with(
            'prefix_core_config_data',
            ['path IN (?)' => [
                ConnectionMode::XML_PATH_TEST_API_KEY,
                ConnectionMode::XML_PATH_PRODUCTION_API_KEY,
            ]]
        );
        $connection->expects(self::once())->method('endSetup');

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }

    public function testToleratesMissingConfigurationTable(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('core_config_data')->willReturn('prefix_core_config_data');
        $connection->method('isTableExists')->with('prefix_core_config_data')->willReturn(false);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::once())->method('endSetup');

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }
}
