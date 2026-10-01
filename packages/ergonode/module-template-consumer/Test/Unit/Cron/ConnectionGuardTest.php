<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Ergonode\TemplateConsumer\Cron\SynchronizeTemplates;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConnectionGuardTest extends ConnectionTestCase
{
    #[DataProvider('unavailableConnections')]
    public function testSynchronizeTemplatesSkipsRepeatedUnavailableConnections(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        $logger = $this->silentLogger();
        $process = $this->createMock(TemplateSynchronizerInterface::class);
        $process->expects(self::never())->method('execute');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(TemplateConfigProvider::class);
        $domain->method('isCronEnabled')->willReturn(true);
        $cron = new SynchronizeTemplates($policy, $config, $domain, $process, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testSynchronizeTemplatesSkipsCredentialsRejectedAfterTheProbe(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->silentLogger();
        $process = $this->createMock(TemplateSynchronizerInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException(
            new ConnectionConfigurationException(
                'Key revoked',
                ConnectionConfigurationException::FAILURE_AUTHORIZATION,
                401
            )
        );
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(TemplateConfigProvider::class);
        $domain->method('isCronEnabled')->willReturn(true);
        (new SynchronizeTemplates($policy, $config, $domain, $process, $logger))->execute();
    }

    public function testSynchronizeTemplatesResumesAfterConnectionRecovery(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger->expects(self::never())->method('error');
        $process = $this->createMock(TemplateSynchronizerInterface::class);
        $process->expects(self::once())->method('execute')->willReturn(['conflicts' => 0]);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(TemplateConfigProvider::class);
        $domain->method('isCronEnabled')->willReturn(true);
        $cron = new SynchronizeTemplates($policy, $config, $domain, $process, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testSynchronizeTemplatesStillReportsUnexpectedProcessFailures(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('info');
        $process = $this->createMock(TemplateSynchronizerInterface::class);
        $process->expects(self::once())->method('execute')->willThrowException(new RuntimeException('Database failed'));
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(TemplateConfigProvider::class);
        $domain->method('isCronEnabled')->willReturn(true);
        $this->expectException(RuntimeException::class);
        (new SynchronizeTemplates($policy, $config, $domain, $process, $logger))->execute();
    }
}
