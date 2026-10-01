<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Setup;

use Ergonode\Publisher\Model\Config\ConnectionMode;
use Ergonode\Publisher\Setup\Uninstall;
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
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturnCallback(
            static fn (string $table): bool => $table === 'prefix_core_config_data'
        );
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('delete')->with(
            'prefix_core_config_data',
            ['path IN (?)' => [
                ConnectionMode::XML_PATH_TEST_API_KEY,
                ConnectionMode::XML_PATH_PRODUCTION_API_KEY,
            ]]
        );
        $connection->expects(self::once())->method('endSetup');

        (new Uninstall())->uninstall(
            $setup,
            $this->createStub(ModuleContextInterface::class)
        );
    }

    public function testToleratesMissingConfigurationTable(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::once())->method('endSetup');

        (new Uninstall())->uninstall(
            $setup,
            $this->createStub(ModuleContextInterface::class)
        );
    }
}
