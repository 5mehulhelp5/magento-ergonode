<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Integration\Setup;

use Ergonode\TemplateAttributeConsumer\Setup\Uninstall;
use Ergonode\TemplateAttributeConsumer\Setup\Uninstall\RuntimeRecordCleaner;
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
    private const array MAPPING_TABLES = [
        'ergonode_template_attribute_ownership',
        'ergonode_template_group_ownership',
    ];

    private const array MAGENTO_EAV_TABLES = [
        'eav_attribute_group',
        'eav_entity_attribute',
    ];

    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    public function testRuntimeCleanerPreservesMagentoEavData(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'etac' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn (string $table): string => $prefix . $table
        );
        $allTables = [
            'core_config_data',
            ...self::MAPPING_TABLES,
            ...self::MAGENTO_EAV_TABLES,
        ];

        try {
            foreach ($allTables as $table) {
                $this->createTable($connection, $prefix . $table, $table === 'core_config_data');
            }
            $connection->insert($prefix . 'core_config_data', [
                'path' => 'ergonode_templates/import/sync_attributes',
                'value' => '1',
            ]);
            $connection->insert($prefix . 'core_config_data', [
                'path' => 'foreign/configuration',
                'value' => 'keep',
            ]);
            foreach (self::MAGENTO_EAV_TABLES as $table) {
                $connection->insert($prefix . $table, ['entity_id' => 1]);
            }

            (new RuntimeRecordCleaner())->execute($setup);

            foreach (self::MAPPING_TABLES as $table) {
                self::assertFalse($connection->isTableExists($prefix . $table), $table);
            }
            foreach (self::MAGENTO_EAV_TABLES as $table) {
                self::assertTrue($connection->isTableExists($prefix . $table), $table);
                self::assertSame(1, (int)$connection->fetchOne(
                    $connection->select()->from($prefix . $table, ['count' => 'COUNT(*)'])
                ));
            }
            self::assertSame(
                ['foreign/configuration'],
                $connection->fetchCol(
                    $connection->select()->from($prefix . 'core_config_data', ['path'])->order('path')
                )
            );
        } finally {
            foreach ($allTables as $table) {
                $tableName = $prefix . $table;
                if ($connection->isTableExists($tableName)) {
                    $connection->dropTable($tableName);
                }
            }
        }
    }

    private function createTable(AdapterInterface $connection, string $tableName, bool $configuration): void
    {
        $table = $connection->newTable($tableName)
            ->addColumn('entity_id', Table::TYPE_INTEGER);
        if ($configuration) {
            $table->addColumn('path', Table::TYPE_TEXT, 255)
                ->addColumn('value', Table::TYPE_TEXT, 255, ['nullable' => true]);
        }
        $connection->createTable($table);
    }
}
