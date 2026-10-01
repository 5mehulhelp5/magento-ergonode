<?php

declare(strict_types=1);

namespace Ergonode\Template\Test\Unit\Setup\Uninstall;

use Ergonode\Template\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testRemovesOwnedTemplateTableAndAclRules(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects(self::once())->method('delete')->with(
            'prefix_authorization_rule',
            ['resource_id IN (?)' => [
                'Ergonode_TemplateConsumer::template_mapping',
                'Ergonode_TemplateConsumer::template_refresh',
                'Ergonode_TemplateConsumer::template_save',
                'Ergonode_TemplateConsumer::template_sync',
            ]]
        );
        $connection->expects(self::once())->method('dropTable')->with('prefix_ergonode_template');

        (new RuntimeRecordCleaner())->execute($setup);
    }

    public function testToleratesCompletelyMissingRuntimeSchema(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('dropTable');

        (new RuntimeRecordCleaner())->execute($setup);
    }
}
