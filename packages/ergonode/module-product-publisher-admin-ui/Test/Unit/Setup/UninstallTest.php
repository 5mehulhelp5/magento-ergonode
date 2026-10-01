<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Test\Unit\Setup;

use Ergonode\ProductPublisherAdminUi\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOnlyOwnedGridBookmarks(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('ui_bookmark')->willReturn('prefix_ui_bookmark');
        $connection->method('isTableExists')->with('prefix_ui_bookmark')->willReturn(true);
        $connection->expects(self::once())->method('delete')->with(
            'prefix_ui_bookmark',
            ['namespace IN (?)' => [
                'ergonode_product_publication_job_listing',
                'ergonode_product_publication_item_listing',
            ]]
        );

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }

    public function testToleratesMissingBookmarkTable(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('ui_bookmark')->willReturn('prefix_ui_bookmark');
        $connection->method('isTableExists')->with('prefix_ui_bookmark')->willReturn(false);
        $connection->expects(self::never())->method('delete');

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }
}
