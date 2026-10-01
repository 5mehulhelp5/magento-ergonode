<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Ergonode\TemplateConsumer\Cron\SynchronizeTemplates;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SynchronizeTemplatesTest extends TestCase
{
    public function testEnabledCronDelegatesToSharedListSynchronizer(): void
    {
        $ergonodeConfig = $this->createStub(ConfigProvider::class);
        $ergonodeConfig->method('isEnabled')->willReturn(true);
        $templateConfig = $this->createStub(TemplateConfigProvider::class);
        $templateConfig->method('isCronEnabled')->willReturn(true);
        $synchronizer = $this->createMock(TemplateSynchronizerInterface::class);
        $result = [
            'events' => 1,
            'imported' => 1,
            'changed' => 1,
            'unchanged' => 0,
            'cursor' => null,
        ];
        $synchronizer->expects(self::once())->method('execute')->with()->willReturn($result);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Ergonode template synchronization cron completed.', $result);

        (new SynchronizeTemplates(
            $this->automation(),
            $ergonodeConfig,
            $templateConfig,
            $synchronizer,
            $logger
        ))->execute();
    }

    public function testDisabledCronDoesNotStartListSynchronization(): void
    {
        $ergonodeConfig = $this->createStub(ConfigProvider::class);
        $ergonodeConfig->method('isEnabled')->willReturn(true);
        $templateConfig = $this->createStub(TemplateConfigProvider::class);
        $templateConfig->method('isCronEnabled')->willReturn(false);
        $synchronizer = $this->createMock(TemplateSynchronizerInterface::class);
        $synchronizer->expects(self::never())->method('execute');

        (new SynchronizeTemplates(
            $this->automation(),
            $ergonodeConfig,
            $templateConfig,
            $synchronizer,
            $this->createStub(LoggerInterface::class)
        ))->execute();
    }
    private function automation(): AutomaticSynchronizationInterface
    {
        $policy = $this->createStub(AutomaticSynchronizationInterface::class);
        $policy->method('isAllowed')->willReturn(true);
        return $policy;
    }
}
