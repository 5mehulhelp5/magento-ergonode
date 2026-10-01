<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Test\Unit\Setup\Uninstall;

use Ergonode\TemplateAttribute\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testDropsOnlyExistingOwnedTablesInDependencySafeOrder(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn (string $table): string => 'prefix_' . $table
        );
        $connection->expects(self::exactly(2))->method('isTableExists')
            ->willReturnCallback(static fn (string $table): bool => $table === 'prefix_ergonode_template_attribute');
        $connection->expects(self::once())->method('dropTable')
            ->with('prefix_ergonode_template_attribute');

        (new RuntimeRecordCleaner())->execute($setup);
    }
}
