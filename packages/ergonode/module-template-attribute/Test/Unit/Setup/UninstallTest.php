<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Test\Unit\Setup;

use Ergonode\TemplateAttribute\Setup\Uninstall;
use Ergonode\TemplateAttribute\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testWrapsRuntimeCleanupInSetupLifecycle(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $cleaner = $this->createMock(RuntimeRecordCleaner::class);
        $sequence = [];
        $connection->expects(self::once())->method('startSetup')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'start';
            });
        $cleaner->expects(self::once())->method('execute')->with($setup)
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'clean';
            });
        $connection->expects(self::once())->method('endSetup')
            ->willReturnCallback(static function () use (&$sequence): void {
                $sequence[] = 'end';
            });

        (new Uninstall($cleaner))->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame(['start', 'clean', 'end'], $sequence);
    }
}
