<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Cron;

use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Cron\ImportCategories;
use Ergonode\CategoryConsumer\Cron\ImportCategoryData;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConnectionGuardTest extends ConnectionTestCase
{
    public function testImportCategoriesReportsRateLimitDuringSynchronization(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $failure = new GraphQlRequestException('Rate limit', GraphQlRequestException::FAILURE_RATE_LIMIT, 429, 30);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        $logger->expects(self::once())->method('error')->with(
            'Ergonode category structure stream request failed.',
            ['message' => 'Rate limit', 'failure_type' => 'rate_limit', 'retry_after_seconds' => 30]
        );
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException($failure);
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        (new ImportCategories($policy, $eligibility, $process, $formatter, $logger))->execute();
    }

    public function testImportCategoryDataReportsRateLimitDuringSynchronization(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $failure = new GraphQlRequestException('Rate limit', GraphQlRequestException::FAILURE_RATE_LIMIT, 429, 30);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        $logger->expects(self::once())->method('error')->with(
            'Ergonode category data stream request failed.',
            ['message' => 'Rate limit', 'failure_type' => 'rate_limit', 'retry_after_seconds' => 30]
        );
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException($failure);
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        (new ImportCategoryData($policy, $eligibility, $process, $formatter, $logger))->execute();
    }

    #[DataProvider('unavailableConnections')]
    public function testImportCategoriesSkipsRepeatedUnavailableConnections(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        $logger = $this->silentLogger();
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::never())->method('execute');
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        $cron = new ImportCategories($policy, $eligibility, $process, $formatter, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testImportCategoriesSkipsCredentialsRejectedAfterTheProbe(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->silentLogger();
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException(
            new ConnectionConfigurationException(
                'Key revoked',
                ConnectionConfigurationException::FAILURE_AUTHORIZATION,
                401
            )
        );
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        (new ImportCategories($policy, $eligibility, $process, $formatter, $logger))->execute();
    }

    #[DataProvider('unavailableConnections')]
    public function testImportCategoryDataSkipsRepeatedUnavailableConnections(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        $logger = $this->silentLogger();
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::never())->method('execute');
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        $cron = new ImportCategoryData($policy, $eligibility, $process, $formatter, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testImportCategoryDataSkipsCredentialsRejectedAfterTheProbe(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->silentLogger();
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException(
            new ConnectionConfigurationException(
                'Key revoked',
                ConnectionConfigurationException::FAILURE_AUTHORIZATION,
                401
            )
        );
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        (new ImportCategoryData($policy, $eligibility, $process, $formatter, $logger))->execute();
    }

    public function testImportCategoriesResumesAfterConnectionRecovery(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger->expects(self::never())->method('error');
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willReturn(['conflicts' => 0]);
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        $cron = new ImportCategories($policy, $eligibility, $process, $formatter, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testImportCategoriesStillReportsUnexpectedProcessFailures(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('info');
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException(new RuntimeException('Database failed'));
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        (new ImportCategories($policy, $eligibility, $process, $formatter, $logger))->execute();
    }

    public function testImportCategoryDataResumesAfterConnectionRecovery(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger->expects(self::never())->method('error');
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willReturn(['conflicts' => 0]);
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        $cron = new ImportCategoryData($policy, $eligibility, $process, $formatter, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testImportCategoryDataStillReportsUnexpectedProcessFailures(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('info');
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException(new RuntimeException('Database failed'));
        $eligibility = $this->createStub(CategoryStreamEligibility::class);
        $eligibility->method('canRunCron')->willReturn(true);
        $formatter = new CategoryReconciliationErrorFormatter();
        (new ImportCategoryData($policy, $eligibility, $process, $formatter, $logger))->execute();
    }
}
