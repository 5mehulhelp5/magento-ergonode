<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Setup;

use Ergonode\Media\Setup\Uninstall;
use Ergonode\Media\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testUninstallOnlyInvokesOwnedTableCleanup(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $records = $this->createMock(RuntimeRecordCleaner::class);
        $sequence = [];
        $connection->expects(self::once())->method('startSetup')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'start';
            });
        $records->expects(self::once())->method('execute')->with($setup)
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'records';
            });
        $connection->expects(self::once())->method('endSetup')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'end';
            });

        (new Uninstall($records))->uninstall(
            $setup,
            $this->createStub(ModuleContextInterface::class)
        );

        self::assertSame(['start', 'records', 'end'], $sequence);
    }
}
