<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Index;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Media\Cron\ScheduleStream;
use Ergonode\Media\Cron\ScanFiles;
use Ergonode\Media\Model\Import\StreamScheduler;
use Ergonode\Media\Model\Index\LocalFileScanner;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class AutomaticCronTest extends TestCase
{
    public function testWriteModeDoesNotStartTheStream(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(false);
        $scheduler = $this->createMock(StreamScheduler::class);
        $scheduler->expects(self::never())->method('schedule');
        (new ScheduleStream($policy, $scheduler, $this->createStub(LoggerInterface::class)))->execute();
    }
    public function testManualScanWorkerStillRunsRequestedScan(): void
    {
        $scanner = $this->createMock(LocalFileScanner::class);
        $scanner->expects(self::once())->method('scan')->with(true)->willReturn(['indexed' => 0,
            'reused' => 0, 'removed' => 0]);
        (new ScanFiles($scanner))->execute();
    }
}
