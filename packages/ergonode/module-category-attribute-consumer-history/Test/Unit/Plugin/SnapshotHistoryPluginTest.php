<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerHistory\Test\Unit\Plugin;

use Ergonode\AttributeConsumer\Api\AttributeSnapshotRefreshInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeSnapshotRemoverInterface;
use Ergonode\CategoryAttributeConsumerHistory\Plugin\SnapshotHistoryPlugin;
use Ergonode\CategoryAttributeHistory\Api\HistoryOperationCaptureInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SnapshotHistoryPluginTest extends TestCase
{
    public function testSharedRefreshPreservesCursorPageSizeAndResult(): void
    {
        $result = ['has_more' => true, 'cursor' => 'next', 'imported' => 7];
        $capture = $this->createMock(HistoryOperationCaptureInterface::class);
        $capture->expects(self::once())->method('execute')->with('refresh_snapshot', self::isCallable())
            ->willReturnCallback(static fn (string $code, callable $operation): mixed => $operation());
        $calls = [];
        $actual = (new SnapshotHistoryPlugin($capture))->aroundRefreshSnapshot(
            $this->createStub(AttributeSnapshotRefreshInterface::class),
            static function (?string $cursor, ?int $pageSize) use (&$calls, $result): array {
                $calls[] = [$cursor, $pageSize];
                return $result;
            },
            'previous',
            25
        );

        self::assertSame([['previous', 25]], $calls);
        self::assertSame($result, $actual);
    }

    public function testRemovalPreservesOriginalException(): void
    {
        $capture = $this->createMock(HistoryOperationCaptureInterface::class);
        $capture->expects(self::once())->method('execute')->with('delete_snapshot', self::isCallable())
            ->willReturnCallback(static fn (string $code, callable $operation): mixed => $operation());
        $failure = new RuntimeException('original failure');
        try {
            (new SnapshotHistoryPlugin($capture))->aroundRemove(
                $this->createStub(CategoryAttributeSnapshotRemoverInterface::class),
                static function (string $code) use ($failure): void {
                    self::assertSame('category_title', $code);
                    throw $failure;
                },
                'category_title'
            );
            self::fail('Original exception must be preserved.');
        } catch (RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }
}
