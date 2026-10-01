<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Test\Integration\Setup;

use Ergonode\TemplateAttribute\Setup\Uninstall;
use Ergonode\TemplateAttribute\Setup\Uninstall\RuntimeRecordCleaner;
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
    private const array OWNED_TABLES = [
        'ergonode_template_attribute',
        'ergonode_template_section',
    ];

    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    public function testRuntimeCleanerDropsOnlyOwnedTables(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'eta' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn (string $table): string => $prefix . $table
        );
        $foreignTable = $prefix . 'ergonode_template';

        try {
            $this->createTable($connection, $foreignTable);
            foreach (self::OWNED_TABLES as $table) {
                $this->createTable($connection, $prefix . $table);
            }

            (new RuntimeRecordCleaner())->execute($setup);

            self::assertTrue($connection->isTableExists($foreignTable));
            foreach (self::OWNED_TABLES as $table) {
                self::assertFalse($connection->isTableExists($prefix . $table), $table);
            }
        } finally {
            foreach ([...self::OWNED_TABLES, 'ergonode_template'] as $table) {
                $tableName = $prefix . $table;
                if ($connection->isTableExists($tableName)) {
                    $connection->dropTable($tableName);
                }
            }
        }
    }

    private function createTable(AdapterInterface $connection, string $tableName): void
    {
        $connection->createTable(
            $connection->newTable($tableName)->addColumn('entity_id', Table::TYPE_INTEGER)
        );
    }
}
