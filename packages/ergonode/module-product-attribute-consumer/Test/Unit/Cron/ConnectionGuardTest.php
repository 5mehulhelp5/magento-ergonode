<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use Ergonode\ProductAttributeConsumer\Cron\ImportAttributes;
use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttributeConsumer\Model\Import\AttributeImportProcess;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConnectionGuardTest extends ConnectionTestCase
{
    #[DataProvider('unavailableConnections')]
    public function testImportAttributesSkipsRepeatedUnavailableConnections(string $scenario): void
    {
        $policy = $this->unavailableAutomation($scenario);
        $logger = $this->silentLogger();
        $process = $this->createMock(AttributeImportProcess::class);
        $process->expects(self::never())->method('executeBatch');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(ProductAttributeConfigProvider::class);
        $domain->method('isImportCronEnabled')->willReturn(true);
        $cron = new ImportAttributes($policy, $config, $domain, $process, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testImportAttributesSkipsCredentialsRejectedAfterTheProbe(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->silentLogger();
        $process = $this->createMock(AttributeImportProcess::class);
        $process->expects(self::once())->method('executeBatch')->willThrowException(
            new ConnectionConfigurationException(
                'Key revoked',
                ConnectionConfigurationException::FAILURE_AUTHORIZATION,
                401
            )
        );
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(ProductAttributeConfigProvider::class);
        $domain->method('isImportCronEnabled')->willReturn(true);
        (new ImportAttributes($policy, $config, $domain, $process, $logger))->execute();
    }

    public function testImportAttributesResumesAfterConnectionRecovery(): void
    {
        $policy = $this->createMock(AutomaticSynchronizationInterface::class);
        $policy->expects(self::exactly(2))->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger->expects(self::never())->method('error');
        $process = $this->createMock(AttributeImportProcess::class);
        $process->expects(self::once())->method('executeBatch')->willReturn(['conflicts' => 0]);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(ProductAttributeConfigProvider::class);
        $domain->method('isImportCronEnabled')->willReturn(true);
        $cron = new ImportAttributes($policy, $config, $domain, $process, $logger);
        $cron->execute();
        $cron->execute();
    }

    public function testImportAttributesStillReportsUnexpectedProcessFailures(): void
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('info');
        $process = $this->createMock(AttributeImportProcess::class);
        $process->expects(self::once())->method('executeBatch')
            ->willThrowException(new RuntimeException('Database failed'));
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $domain = $this->createStub(ProductAttributeConfigProvider::class);
        $domain->method('isImportCronEnabled')->willReturn(true);
        $this->expectException(RuntimeException::class);
        (new ImportAttributes($policy, $config, $domain, $process, $logger))->execute();
    }
}
