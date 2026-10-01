<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerHistory\Test\Unit\Plugin;

use Ergonode\ProductAttributeConsumer\Api\AttributeAutoMapperInterface;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationBatchInterface;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationProcessInterface;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeConsumerHistory\Plugin\AutoMappingHistoryPlugin;
use Ergonode\ProductAttributeConsumerHistory\Plugin\BatchHistoryPlugin;
use Ergonode\ProductAttributeConsumerHistory\Plugin\MappingSaveHistoryPlugin;
use Ergonode\ProductAttributeConsumerHistory\Plugin\SynchronizationHistoryPlugin;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CapturePluginsTest extends TestCase
{
    /**
     * @param class-string $pluginClass
     * @param class-string $subjectClass
     * @param list<mixed> $arguments
     */
    #[DataProvider('operations')]
    public function testPreservesArgumentsResultAndOriginalFailure(
        string $pluginClass,
        string $subjectClass,
        string $method,
        string $code,
        array $arguments
    ): void {
        $capture = $this->createMock(HistoryOperationCaptureInterface::class);
        $capture->expects(self::exactly(2))->method('execute')->with($code, self::isCallable())
            ->willReturnCallback(static fn (string $operationCode, callable $operation): mixed => $operation());
        $plugin = new $pluginClass($capture);
        $subject = $this->createStub($subjectClass);
        $result = ['processed' => 2, 'cursor' => 'next'];
        $calls = 0;
        $proceed = static function (...$received) use ($arguments, $result, &$calls): array {
            self::assertSame($arguments, $received);
            $calls++;
            return $result;
        };

        self::assertSame($result, $plugin->$method($subject, $proceed, ...$arguments));
        self::assertSame(1, $calls);
        $failure = new RuntimeException('Original inbound failure');
        try {
            $plugin->$method($subject, static fn () => throw $failure, ...$arguments);
            self::fail('The original failure must escape.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return array<string, array{class-string, class-string, string, string, list<mixed>}> */
    public static function operations(): array
    {
        return [
            'manual save' => [MappingSaveHistoryPlugin::class, AttributeMappingSaver::class,
                'aroundSave', 'save', [[['left' => ['code' => 'color']]], [['active' => false]]]],
            'additive save' => [MappingSaveHistoryPlugin::class, AttributeMappingSaver::class,
                'aroundSaveAdditions', 'auto_map', [[['left' => ['code' => 'color']]]]],
            'automatic mapping' => [AutoMappingHistoryPlugin::class, AttributeAutoMapperInterface::class,
                'aroundSynchronize', 'auto_map', []],
            'batch' => [BatchHistoryPlugin::class, AttributeSynchronizationBatchInterface::class,
                'aroundExecuteAutomatic', 'synchronize', ['cursor', 17, true]],
            'prepared batch' => [BatchHistoryPlugin::class, AttributeSynchronizationBatchInterface::class,
                'aroundExecuteAutomatic', 'synchronize', ['cursor', 17, false]],
            'complete run' => [SynchronizationHistoryPlugin::class, AttributeSynchronizationProcessInterface::class,
                'aroundExecuteUntilComplete', 'synchronize', [23, 4]],
        ];
    }
}
