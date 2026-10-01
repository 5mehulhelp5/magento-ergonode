<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Test\Unit\Plugin;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\SaveContext;
use Ergonode\CategoryAttributeConsumer\Api\MappingSynchronizationInterface;
use Ergonode\CategoryAttributeConsumerAdminUi\Plugin\MappingSynchronizationPlugin;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MappingSynchronizationPluginTest extends TestCase
{
    public function testNeutralSaveRunsOnceInsideConsumerContext(): void
    {
        $events = [];
        $synchronization = $this->createMock(MappingSynchronizationInterface::class);
        $synchronization->expects(self::once())->method('execute')->willReturnCallback(
            static function (callable $save) use (&$events): array {
                $events[] = 'lock';
                $result = $save();
                $events[] = 'backfill';
                return $result + ['value_sync' => ['values' => 1]];
            }
        );
        $save = static function () use (&$events): array {
            $events[] = 'save';
            return ['inserted' => 1];
        };
        $context = new SaveContext();
        $result = (new MappingSynchronizationPlugin($synchronization))->aroundExecute(
            $context,
            $context->execute(...),
            $save
        );
        self::assertSame(['lock', 'save', 'backfill'], $events);
        self::assertSame(['inserted' => 1, 'value_sync' => ['values' => 1]], $result);
    }

    public function testNeutralContextPropagatesSaveFailure(): void
    {
        $this->expectExceptionMessage('save failed');
        (new SaveContext())->execute(static function (): array {
            throw new RuntimeException('save failed');
        });
    }
}
