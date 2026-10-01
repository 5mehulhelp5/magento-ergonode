<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationCheckpoint;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\PendingCategoryPublisher;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PendingCategoryPublisherTest extends TestCase
{
    public function testPreparedCategoryIsNotPublishedAgainDuringFinalTreeSave(): void
    {
        $batch = $this->createMock(CategoryBatchPublisher::class);
        $batch->expects(self::never())->method('publish');
        (new PendingCategoryPublisher($batch, $this->createStub(CategoryPublicationCheckpoint::class)))->publish(7, [[
            'code' => 'chairs',
            'extension_data' => ['to_ergonode' => ['pending_create' => true, 'remote_prepared' => true]],
        ]]);
    }

    public function testPartialSuccessIsCheckpointedAndRetryOnlyCreatesFailedItems(): void
    {
        $completed = [];
        $checkpoint = $this->createMock(CategoryPublicationCheckpoint::class);
        $checkpoint->method('get')->willReturnCallback(static function () use (&$completed): array {
            return $completed;
        });
        $checkpoint->method('save')->willReturnCallback(
            static function (int $treeId, array $items, array $codes, string $operation) use (&$completed): void {
                self::assertSame('layout', $operation);
                $completed = array_fill_keys($codes, true);
            }
        );
        $checkpoint->expects(self::once())->method('clear')->with(7, self::anything(), 'layout');
        $batch = $this->createMock(CategoryBatchPublisher::class);
        $calls = [];
        $batch->expects(self::exactly(2))->method('publish')->willReturnCallback(
            static function (int $treeId, array $items) use (&$calls): array {
                $calls[] = array_column($items, 'code');
                return count($calls) === 1 ? [
                    ['code' => 'a', 'status' => 'synchronized', 'message' => 'Created'],
                    ['code' => 'b', 'status' => 'failed', 'message' => 'Temporary failure'],
                ] : [['code' => 'b', 'status' => 'synchronized', 'message' => 'Created']];
            }
        );
        $publisher = new PendingCategoryPublisher($batch, $checkpoint);
        $items = array_map(static fn (string $code): array => [
            'code' => $code, 'extension_data' => ['to_ergonode' => ['pending_create' => true]],
        ], ['a', 'b']);
        try {
            $publisher->publish(7, $items);
            self::fail('The failed item must stop finalization.');
        } catch (LocalizedException $exception) {
            self::assertSame('Temporary failure', $exception->getMessage());
        }
        $publisher->publish(7, $items);
        $publisher->publish(7, $items);
        self::assertSame([['a', 'b'], ['b']], $calls);
        $publisher->complete(7, $items);
    }

    public function testLargeLayoutsUseFiftyItemBatches(): void
    {
        $batch = $this->createMock(CategoryBatchPublisher::class);
        $sizes = [];
        $batch->expects(self::exactly(2))->method('publish')->willReturnCallback(
            static function (int $treeId, array $items) use (&$sizes): array {
                $sizes[] = count($items);
                return array_map(static fn (array $item): array => [
                    'code' => $item['code'], 'status' => 'synchronized', 'message' => 'Created',
                ], $items);
            }
        );
        $items = array_map(static fn (int $index): array => [
            'code' => 'category_' . $index,
            'extension_data' => ['to_ergonode' => ['pending_create' => true]],
        ], range(1, 51));
        (new PendingCategoryPublisher($batch, $this->createStub(CategoryPublicationCheckpoint::class)))
            ->publish(7, $items);
        self::assertSame([50, 1], $sizes);
    }

    public function testUnorderedLayoutCreatesParentBeforeChildrenAcrossBatches(): void
    {
        $batch = $this->createMock(CategoryBatchPublisher::class);
        $codes = [];
        $batch->expects(self::exactly(2))->method('publish')->willReturnCallback(
            static function (int $treeId, array $items) use (&$codes): array {
                $codes[] = array_column($items, 'code');
                return array_map(static fn (array $item): array => [
                    'code' => $item['code'], 'status' => 'synchronized', 'message' => 'Created',
                ], $items);
            }
        );
        $items = array_map(static fn (int $index): array => [
            'code' => 'child_' . $index, 'parent_code' => 'parent',
            'extension_data' => ['to_ergonode' => ['pending_create' => true]],
        ], range(1, 50));
        $items[] = ['code' => 'parent', 'extension_data' => ['to_ergonode' => ['pending_create' => true]]];
        (new PendingCategoryPublisher($batch, $this->createStub(CategoryPublicationCheckpoint::class)))
            ->publish(7, $items);
        self::assertSame('parent', $codes[0][0]);
        self::assertSame(['child_50'], $codes[1]);
    }

    public function testLaterBatchKeepsItsFingerprintWhenEarlierParentsAreAlreadyCompleted(): void
    {
        $items = [['code' => 'b_child', 'parent_code' => 'b']];
        foreach (range(1, 49) as $index) {
            $items[] = ['code' => 'a_child_' . $index, 'parent_code' => 'a'];
        }
        $items[] = ['code' => 'a'];
        $items[] = ['code' => 'b'];
        $items = array_map(static fn (array $item): array => $item + [
            'extension_data' => ['to_ergonode' => ['pending_create' => true]],
        ], $items);
        $completed = [];
        $checkpoint = $this->createStub(CategoryPublicationCheckpoint::class);
        $checkpoint->method('get')->willReturnCallback(static function () use (&$completed): array {
            return $completed;
        });
        $checkpoint->method('save')->willReturnCallback(
            static function (int $treeId, array $layout, array $codes) use (&$completed): void {
                $completed = array_fill_keys($codes, true);
            }
        );
        $calls = [];
        $batch = $this->createMock(CategoryBatchPublisher::class);
        $batch->expects(self::exactly(3))->method('publish')->willReturnCallback(
            static function (int $treeId, array $batchItems) use (&$calls): array {
                $calls[] = $batchItems;
                if (count($calls) === 2) {
                    throw new RuntimeException('REST delayed after creation');
                }
                return array_map(static fn (array $item): array => [
                    'code' => $item['code'], 'status' => 'synchronized', 'message' => 'Created',
                ], $batchItems);
            }
        );
        $publisher = new PendingCategoryPublisher($batch, $checkpoint);
        try {
            $publisher->publish(7, $items);
            self::fail('Expected partial batch interruption.');
        } catch (RuntimeException $exception) {
            self::assertSame('REST delayed after creation', $exception->getMessage());
        }
        $publisher->publish(7, $items);
        self::assertSame($calls[1], $calls[2]);
        self::assertSame(['a_child_49', 'b_child'], array_column($calls[2], 'code'));
    }
}
