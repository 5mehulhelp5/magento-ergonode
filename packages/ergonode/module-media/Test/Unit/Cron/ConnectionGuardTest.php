<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use Ergonode\Media\Cron\ScheduleStream;
use Ergonode\Media\Model\Import\StreamScheduler;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConnectionGuardTest extends ConnectionTestCase
{
    #[DataProvider('unavailableConnections')]
    public function testScheduleStreamSkipsRepeatedUnavailableConnections(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        $logger = $this->silentLogger();
        $process = $this->createMock(StreamScheduler::class);
        $process->expects(self::never())->method('schedule');

        $cron = new ScheduleStream($policy, $process, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testScheduleStreamSkipsCredentialsRejectedAfterTheProbe(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->silentLogger();
        $process = $this->createMock(StreamScheduler::class);
        $process->expects(self::once())->method('schedule')->willThrowException(
            new ConnectionConfigurationException(
                'Key revoked',
                ConnectionConfigurationException::FAILURE_AUTHORIZATION,
                401
            )
        );

        (new ScheduleStream($policy, $process, $logger))->execute();
    }

    public function testScheduleStreamResumesAfterConnectionRecovery(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->silentLogger();
        $process = $this->createMock(StreamScheduler::class);
        $process->expects(self::once())->method('schedule')->willReturn(['pages' => 0, 'assets' => 0, 'products' => 0]);

        $cron = new ScheduleStream($policy, $process, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testScheduleStreamStillReportsUnexpectedProcessFailures(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('info');
        $process = $this->createMock(StreamScheduler::class);
        $process->expects(self::once())->method('schedule')
            ->willThrowException(new RuntimeException('Database failed'));

        (new ScheduleStream($policy, $process, $logger))->execute();
    }
}
