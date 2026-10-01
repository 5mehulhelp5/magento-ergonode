<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Setup\Patch\Schema;

use Ergonode\ProductPublisher\Setup\Patch\Schema\DropLegacyCatalogPublisherTables;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DropLegacyCatalogPublisherTablesTest extends TestCase
{
    private ModuleDataSetupInterface&MockObject $moduleDataSetup;
    private AdapterInterface&MockObject $connection;

    protected function setUp(): void
    {
        $this->moduleDataSetup = $this->createMock(ModuleDataSetupInterface::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->moduleDataSetup->method('getConnection')->willReturn($this->connection);
        $this->moduleDataSetup->method('getTable')->willReturnCallback(
            static fn (string $table): string => 'prefix_' . $table
        );
    }

    public function testDropsLegacyTablesInForeignKeySafeOrder(): void
    {
        $tables = [
            'ergonode_product_publication_item',
            'ergonode_product_publication_job',
        ];
        $this->connection->expects(self::once())->method('startSetup');
        $this->connection->expects(self::exactly(count($tables)))
            ->method('dropTable')
            ->with(self::callback(static function (string $table) use (&$tables): bool {
                return $table === 'prefix_' . array_shift($tables);
            }));
        $this->connection->expects(self::once())->method('endSetup');

        $patch = new DropLegacyCatalogPublisherTables($this->moduleDataSetup);

        self::assertSame($patch, $patch->apply());
        self::assertSame([], $tables);
    }
}
