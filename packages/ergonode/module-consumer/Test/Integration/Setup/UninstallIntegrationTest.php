<?php

declare(strict_types=1);

namespace Ergonode\Consumer\Test\Integration\Setup;

use Ergonode\Consumer\Model\Config\ConnectionMode;
use Ergonode\Consumer\Setup\Uninstall;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class UninstallIntegrationTest extends TestCase
{
    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    public function testRemovesOnlyOwnedWriteConfiguration(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $configTable = 'epu' . bin2hex(random_bytes(4)) . '_core_config_data';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('core_config_data')->willReturn($configTable);

        try {
            $this->createConfigurationTable($connection, $configTable);
            $connection->insertMultiple($configTable, [
                ['path' => ConnectionMode::XML_PATH_PRODUCTION_API_KEY],
                ['path' => ConnectionMode::XML_PATH_TEST_API_KEY],
                ['path' => 'ergonode_connection/general/mode'],
            ]);

            (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

            self::assertSame(
                ['ergonode_connection/general/mode'],
                $connection->fetchCol($connection->select()->from($configTable, ['path']))
            );
        } finally {
            if ($connection->isTableExists($configTable)) {
                $connection->dropTable($configTable);
            }
        }
    }

    private function createConfigurationTable(AdapterInterface $connection, string $configTable): void
    {
        $connection->createTable(
            $connection->newTable($configTable)
                ->addColumn('path', Table::TYPE_TEXT, 255)
        );
    }
}
