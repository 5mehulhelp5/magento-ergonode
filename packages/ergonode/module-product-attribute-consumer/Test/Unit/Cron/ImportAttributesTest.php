<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;

use Ergonode\ProductAttributeConsumer\Cron\ImportAttributes;
use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttributeConsumer\Model\Import\AttributeImportProcess;
use Ergonode\Core\Model\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ImportAttributesTest extends TestCase
{
    public function testDisabledIntegrationDoesNotStartImport(): void
    {
        $config = $this->createMock(ConfigProvider::class);
        $config->expects(self::once())->method('isEnabled')->willReturn(false);
        $attributeConfig = $this->createMock(ProductAttributeConfigProvider::class);
        $attributeConfig->expects(self::never())->method('isImportCronEnabled');
        $process = $this->createMock(AttributeImportProcess::class);
        $process->expects(self::never())->method('executeBatch');

        (new ImportAttributes(
            $this->automation(),
            $config,
            $attributeConfig,
            $process,
            $this->createStub(LoggerInterface::class)
        ))->execute();
    }

    public function testImportFailureIsLoggedAndRethrownToCronRunner(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $attributeConfig = $this->createStub(ProductAttributeConfigProvider::class);
        $attributeConfig->method('isImportCronEnabled')->willReturn(true);
        $failure = new RuntimeException('API unavailable');
        $process = $this->createMock(AttributeImportProcess::class);
        $process->expects(self::once())->method('executeBatch')->willThrowException($failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Ergonode attribute and option synchronization cron batch failed.', ['exception' => $failure]);

        $this->expectExceptionObject($failure);

        (new ImportAttributes($this->automation(), $config, $attributeConfig, $process, $logger))->execute();
    }

    public function testRunsCanonicalAttributeAndOptionProcessOnce(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $attributeConfig = $this->createStub(ProductAttributeConfigProvider::class);
        $attributeConfig->method('isImportCronEnabled')->willReturn(true);
        $result = [
            'imported' => 2,
            'option_mappings' => 1,
            'options' => ['created' => 2],
        ];
        $attributeProcess = $this->createMock(AttributeImportProcess::class);
        $attributeProcess->expects(self::once())->method('executeBatch')->willReturn($result);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'Ergonode attribute and option synchronization cron batch completed.',
            ['result' => $result]
        );

        (new ImportAttributes(
            $this->automation(),
            $config,
            $attributeConfig,
            $attributeProcess,
            $logger
        ))->execute();
    }
    private function automation(): AutomaticSynchronizationInterface
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        return $policy;
    }
}
