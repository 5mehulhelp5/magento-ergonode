<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Integration\Setup;

use Ergonode\Publisher\Model\Config\ConnectionMode;
use Ergonode\Publisher\Setup\Uninstall;
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
        $tokenTable = $configTable . '_ergonode_publisher_rest_connection';
        $ruleTable = $configTable . '_authorization_rule';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn (string $table): string => $table === 'core_config_data'
                ? $configTable : $configTable . '_' . $table
        );

        try {
            $this->createConfigurationTable($connection, $configTable);
            $connection->createTable($connection->newTable($tokenTable)->addColumn('token', Table::TYPE_TEXT));
            $connection->createTable(
                $connection->newTable($ruleTable)->addColumn('resource_id', Table::TYPE_TEXT, 255)
            );
            $connection->insert($tokenTable, ['token' => 'encrypted-fixture']);
            $connection->insertMultiple($ruleTable, [
                ['resource_id' => 'Ergonode_Publisher::rest_connection'],
                ['resource_id' => 'Unrelated_Module::resource'],
            ]);
            $connection->insertMultiple($configTable, [
                ['path' => ConnectionMode::XML_PATH_PRODUCTION_API_KEY],
                ['path' => ConnectionMode::XML_PATH_TEST_API_KEY],
                ['path' => 'ergonode_connection/general/mode'],
            ]);

            $uninstall = new Uninstall();
            $uninstall->uninstall($setup, $this->createStub(ModuleContextInterface::class));
            $uninstall->uninstall($setup, $this->createStub(ModuleContextInterface::class));
            self::assertFalse($connection->isTableExists($tokenTable));
            self::assertSame(['Unrelated_Module::resource'], $connection->fetchCol(
                $connection->select()->from($ruleTable, ['resource_id'])
            ));

            self::assertSame(
                ['ergonode_connection/general/mode'],
                $connection->fetchCol($connection->select()->from($configTable, ['path']))
            );
        } finally {
            foreach ([$configTable, $tokenTable, $ruleTable] as $table) {
                if ($connection->isTableExists($table)) {
                    $connection->dropTable($table);
                }
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
