<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Setup\Uninstall;

use Ergonode\TemplateAttributeConsumer\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testRemovesOnlyConfigurationAndMappingTablesOwnedByModule(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn (string $table): string => 'prefix_' . $table
        );
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects(self::once())->method('delete')
            ->with('prefix_core_config_data', ['path IN (?)' => [
                'ergonode_templates/import/sync_attributes',
                'ergonode_templates/import/sync_sections',
            ]]);
        $droppedTables = [];
        $connection->expects(self::exactly(3))->method('dropTable')
            ->willReturnCallback(static function (string $table) use (&$droppedTables): void {
                $droppedTables[] = $table;
            });

        (new RuntimeRecordCleaner())->execute($setup);

        self::assertSame([
            'prefix_ergonode_template_manual_placement',
            'prefix_ergonode_template_attribute_ownership',
            'prefix_ergonode_template_group_ownership',
        ], $droppedTables);
    }
}
