<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use Ergonode\Media\Cron\RecoverWork;
use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\Media\Model\Queue\QueuePublisher;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class RecoverWorkTest extends ConnectionTestCase
{
    #[DataProvider('unavailableConnections')]
    public function testWaitingWorkIsNotPublishedWithoutAConnection(string $scenario): void
    {
        $repository = $this->createStub(MediaRepositoryInterface::class);
        $repository->method('hasWork')->willReturn(true);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $cron = new RecoverWork(
            $this->unavailableAutomation($scenario),
            $repository,
            $publisher,
            $config,
            $this->createStub(ScanReadiness::class)
        );
        $cron->execute();
        $cron->execute();
    }

    /** @return array<string, array{bool, bool, bool}> */
    public static function localBlockers(): array
    {
        return ['disabled' => [false, false, true], 'scan blocked' => [true, true, true],
            'no work' => [true, false, false]];
    }

    #[DataProvider('localBlockers')]
    public function testLocalBlockersAvoidTheRemoteProbe(bool $enabled, bool $blocked, bool $hasWork): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::never())->method('isAllowed');
        $repository = $this->createStub(MediaRepositoryInterface::class);
        $repository->method('hasWork')->willReturn($hasWork);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn($enabled);
        $readiness = $this->createStub(ScanReadiness::class);
        $readiness->method('isBlocked')->willReturn($blocked);
        (new RecoverWork($policy, $repository, $publisher, $config, $readiness))->execute();
    }

    public function testRecoveryResumesAfterTheConnectionIsCorrected(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $repository = $this->createStub(MediaRepositoryInterface::class);
        $repository->method('hasWork')->willReturn(true);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::once())->method('dispatch');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $cron = new RecoverWork($policy, $repository, $publisher, $config, $this->createStub(ScanReadiness::class));
        $cron->execute();
        $cron->execute();
    }

    public function testRepositoryFailureRemainsVisible(): void
    {
        $repository = $this->createStub(MediaRepositoryInterface::class);
        $repository->method('hasWork')->willThrowException(new RuntimeException('Database failed'));
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $this->expectException(RuntimeException::class);
        (new RecoverWork(
            $this->createStub(AutomaticSynchronizationInterface::class),
            $repository,
            $publisher,
            $config,
            $this->createStub(ScanReadiness::class)
        ))->execute();
    }
}
