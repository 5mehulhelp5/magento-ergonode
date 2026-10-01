<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use Ergonode\ProductConsumer\Cron\RecoverProductImports;
use Ergonode\ProductConsumer\Cron\ScheduleProductImports;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\Import\ProductStreamScheduler;
use Ergonode\ProductConsumer\Model\Queue\ProductImportRecoveryDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConnectionGuardTest extends ConnectionTestCase
{
    #[DataProvider('unavailableConnections')]
    public function testScheduleProductImportsSkipsRepeatedUnavailableConnections(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        $logger = $this->silentLogger();
        $process = $this->createMock(ProductStreamScheduler::class);
        $process->expects(self::never())->method('schedule');
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $cron = new ScheduleProductImports($policy, $process, $logger, $config);
        $cron->execute();
        $cron->execute();
    }

    public function testScheduleProductImportsSkipsCredentialsRejectedAfterTheProbe(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->silentLogger();
        $process = $this->createMock(ProductStreamScheduler::class);
        $process->expects(self::once())->method('schedule')->willThrowException(
            new ConnectionConfigurationException(
                'Key revoked',
                ConnectionConfigurationException::FAILURE_AUTHORIZATION,
                401
            )
        );
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        (new ScheduleProductImports($policy, $process, $logger, $config))->execute();
    }

    #[DataProvider('unavailableConnections')]
    public function testRecoverProductImportsSkipsRepeatedUnavailableConnections(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        $logger = $this->silentLogger();
        $process = $this->createMock(ProductImportRecoveryDispatcher::class);
        $process->expects(self::never())->method('dispatch');
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $cron = new RecoverProductImports($policy, $process, $logger, $config);
        $cron->execute();
        $cron->execute();
    }

    public function testRecoverProductImportsSkipsCredentialsRejectedAfterTheProbe(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->silentLogger();
        $process = $this->createMock(ProductImportRecoveryDispatcher::class);
        $process->expects(self::once())->method('dispatch')->willThrowException(
            new ConnectionConfigurationException(
                'Key revoked',
                ConnectionConfigurationException::FAILURE_AUTHORIZATION,
                401
            )
        );
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        (new RecoverProductImports($policy, $process, $logger, $config))->execute();
    }

    public function testScheduleProductImportsResumesAfterConnectionRecovery(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->silentLogger();
        $process = $this->createMock(ProductStreamScheduler::class);
        $process->expects(self::once())->method('schedule')
            ->willReturn(['pages' => 0, 'changed' => 0, 'deleted' => 0, 'throttled' => false]);
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $cron = new ScheduleProductImports($policy, $process, $logger, $config);
        $cron->execute();
        $cron->execute();
    }

    public function testScheduleProductImportsStillReportsUnexpectedProcessFailures(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('info');
        $process = $this->createMock(ProductStreamScheduler::class);
        $process->expects(self::once())->method('schedule')
            ->willThrowException(new RuntimeException('Database failed'));
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        (new ScheduleProductImports($policy, $process, $logger, $config))->execute();
    }

    public function testRecoverProductImportsResumesAfterConnectionRecovery(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->silentLogger();
        $process = $this->createMock(ProductImportRecoveryDispatcher::class);
        $process->expects(self::once())->method('dispatch');
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $cron = new RecoverProductImports($policy, $process, $logger, $config);
        $cron->execute();
        $cron->execute();
    }

    public function testRecoverProductImportsStillReportsUnexpectedProcessFailures(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('info');
        $process = $this->createMock(ProductImportRecoveryDispatcher::class);
        $process->expects(self::once())->method('dispatch')
            ->willThrowException(new RuntimeException('Database failed'));
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(true);
        (new RecoverProductImports($policy, $process, $logger, $config))->execute();
    }

    public function testScheduleProductImportsDoesNotProbeWhenProductImportIsDisabled(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::never())->method('isAllowed');
        $process = $this->createMock(ProductStreamScheduler::class);
        $process->expects(self::never())->method('schedule');
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(false);
        (new ScheduleProductImports($policy, $process, $this->silentLogger(), $config))->execute();
    }

    public function testRecoverProductImportsDoesNotProbeWhenProductImportIsDisabled(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::never())->method('isAllowed');
        $process = $this->createMock(ProductImportRecoveryDispatcher::class);
        $process->expects(self::never())->method('dispatch');
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('isEnabled')->willReturn(false);
        (new RecoverProductImports($policy, $process, $this->silentLogger(), $config))->execute();
    }
}
