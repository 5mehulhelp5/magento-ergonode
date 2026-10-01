<?php

declare(strict_types=1);

namespace Ergonode\Template\Test\Integration\Setup;

use Ergonode\Template\Setup\Uninstall;
use Ergonode\Template\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
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

    public function testRuntimeCleanerDropsTemplateTableAndRemovesOnlyTemplateAclRules(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'et' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => $prefix . $table);
        $authorizationTable = $prefix . 'authorization_rule';
        $templateTable = $prefix . 'ergonode_template';

        try {
            $this->createTable($connection, $authorizationTable, 'resource_id');
            $this->createTable($connection, $templateTable, 'code');
            $connection->insertMultiple($authorizationTable, [
                ['resource_id' => 'Ergonode_TemplateConsumer::template_mapping'],
                ['resource_id' => 'Ergonode_AttributeConsumer::attribute_mapping'],
            ]);

            (new RuntimeRecordCleaner())->execute($setup);

            self::assertFalse($connection->isTableExists($templateTable));
            self::assertSame(
                ['Ergonode_AttributeConsumer::attribute_mapping'],
                $connection->fetchCol($connection->select()->from($authorizationTable, ['resource_id']))
            );
        } finally {
            foreach ([$authorizationTable, $templateTable] as $table) {
                if ($connection->isTableExists($table)) {
                    $connection->dropTable($table);
                }
            }
        }
    }

    private function createTable(AdapterInterface $connection, string $tableName, string $column): void
    {
        $connection->createTable(
            $connection->newTable($tableName)->addColumn($column, Table::TYPE_TEXT, 255)
        );
    }
}
