<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerHistory\Test\Unit\Plugin;

use Ergonode\CategoryAttributeConsumer\Api\MappingSynchronizationInterface;
use Ergonode\CategoryAttributeConsumerHistory\Plugin\MappingSaveHistoryPlugin;
use Ergonode\CategoryAttributeHistory\Api\HistoryOperationCaptureInterface;
use PHPUnit\Framework\TestCase;

class MappingSaveHistoryPluginTest extends TestCase
{
    public function testCapturesSaveCallbackAndItsSynchronizationResult(): void
    {
        $save = static fn (): array => ['updated' => 1];
        $capture = $this->createMock(HistoryOperationCaptureInterface::class);
        $capture->expects(self::once())->method('execute')->with('save', self::isCallable())
            ->willReturnCallback(static fn (string $code, callable $operation): array => $operation());
        $result = (new MappingSaveHistoryPlugin($capture))->aroundExecute(
            $this->createStub(MappingSynchronizationInterface::class),
            static function (callable $actual) use ($save): array {
                self::assertSame($save, $actual);
                return $actual() + ['value_sync' => ['categories' => 1, 'values' => 1, 'errors' => 0]];
            },
            $save
        );
        self::assertSame(['updated' => 1, 'value_sync' => ['categories' => 1, 'values' => 1, 'errors' => 0]], $result);
    }
}
