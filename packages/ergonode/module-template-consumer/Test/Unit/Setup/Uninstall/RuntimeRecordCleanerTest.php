<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Setup\Uninstall;

use Ergonode\TemplateConsumer\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testRemovesOwnedCronConfigurationCursorAndVisibilityRecords(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $deletes = [
            ['prefix_cron_schedule', ['job_code = ?' => 'ergonode_template_synchronize']],
            ['prefix_core_config_data', ['path LIKE ?' => 'ergonode_templates/%']],
            ['prefix_ergonode_import_cursor', ['process_code IN (?)' => [
                'template_stream',
                'templateList',
            ]]],
            ['prefix_ergonode_mapping_visibility', ['entity_type = ?' => 'template']],
        ];
        $connection->expects(self::exactly(count($deletes)))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $where) use (&$deletes): int {
                self::assertSame(array_shift($deletes), [$table, $where]);

                return 1;
            });

        (new RuntimeRecordCleaner())->execute($setup);

        self::assertSame([], $deletes);
    }

    public function testToleratesCompletelyMissingRuntimeSchema(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');

        (new RuntimeRecordCleaner())->execute($setup);
    }
}
