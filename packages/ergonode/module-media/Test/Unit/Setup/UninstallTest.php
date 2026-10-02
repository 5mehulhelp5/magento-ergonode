<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Setup;

use Ergonode\Media\Setup\Uninstall;
use Ergonode\Media\Setup\Uninstall\FilesystemCleaner;
use Ergonode\Media\Setup\Uninstall\ProductReferenceCleaner;
use Ergonode\Media\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testCleansDatabaseBeforeOwnedFiles(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $references = $this->createMock(ProductReferenceCleaner::class);
        $records = $this->createMock(RuntimeRecordCleaner::class);
        $files = $this->createMock(FilesystemCleaner::class);
        $sequence = [];
        $connection->expects(self::once())->method('startSetup')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'start';
            });
        $references->expects(self::once())->method('execute')->with($setup)
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'references';
            });
        $records->expects(self::once())->method('execute')->with($setup)
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'records';
            });
        $connection->expects(self::once())->method('endSetup')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'end';
            });
        $files->expects(self::once())->method('execute')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'files';
            });

        (new Uninstall($references, $records, $files))->uninstall(
            $setup,
            $this->createStub(ModuleContextInterface::class)
        );

        self::assertSame(['start', 'references', 'records', 'end', 'files'], $sequence);
    }
}
