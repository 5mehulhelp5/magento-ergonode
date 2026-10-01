<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Console\Command;

use Ergonode\Core\Console\Report\ChangeReportConsoleWriter;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Ergonode\TemplateConsumer\Console\Command\ImportTemplatesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ImportTemplatesCommandTest extends TestCase
{
    public function testDelegatesOneFullListSynchronization(): void
    {
        $synchronizer = $this->createMock(TemplateSynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('execute')->with(true)->willReturn([
            'events' => 2,
            'imported' => 2,
            'changed' => 1,
            'unchanged' => 1,
            'cursor' => null,
        ]);
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects(self::once())->method('reset');
        $command = new ImportTemplatesCommand(
            $synchronizer,
            $changeReport,
            $this->createStub(ChangeReportConsoleWriter::class)
        );
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['--reset' => true]));
        self::assertStringContainsString(
            'Templates events=2 imported=2 changed=1 unchanged=1 cursor=-',
            $tester->getDisplay()
        );
    }
}
