<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationActionInterface;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\CategoryConsumer\Model\ResourceModel\CategorySynchronizationState;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationRun;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CategorySynchronizationRunTest extends TestCase
{
    private const string RUN_ID = '0123456789abcdef0123456789abcdef';
    /** @var array<string, string> */
    private array $cache = [];
    private CategorySynchronizationState $storage;
    private CategorySynchronizationProgress $progress;
    private CategorySynchronizationLock $lock;

    protected function setUp(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn (string $key): string|false => $this->cache[$key] ?? false);
        $cache->method('save')->willReturnCallback(function (string $value, string $key): bool {
            $this->cache[$key] = $value;
            return true;
        });
        $cache->method('remove')->willReturnCallback(function (string $key): bool {
            unset($this->cache[$key]);
            return true;
        });
        $this->storage = new CategorySynchronizationState($cache, new Json());
        $this->progress = new CategorySynchronizationProgress($this->storage);
        $locked = false;
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturnCallback(static function () use (&$locked): bool {
            if ($locked) {
                return false;
            }
            return $locked = true;
        });
        $manager->method('unlock')->willReturnCallback(static function () use (&$locked): bool {
            $locked = false;
            return true;
        });
        $manager->method('isLocked')->willReturnCallback(static function () use (&$locked): bool {
            return $locked;
        });
        $this->lock = new CategorySynchronizationLock($manager);
    }

    public function testDownloadCountersAccumulatePerTreeAndSurviveStageChanges(): void
    {
        $this->storage->save(self::RUN_ID, ['state' => 'running']);
        $this->progress->attach(self::RUN_ID, ['state' => 'running']);
        $this->progress->startTree('tghome_1', 3, 3);
        $this->progress->downloadedPage('tghome_1', true, 700);
        $this->progress->downloadedPage('tghome_1', false, 700);
        $state = $this->storage->get(self::RUN_ID);
        self::assertSame(1400, $state['downloaded']);
        self::assertSame(2, $state['pages']);
        self::assertNull($state['total']);
        $this->progress->checkpoint('comparing_tree');
        self::assertSame(1400, $this->storage->get(self::RUN_ID)['downloaded']);
        $this->progress->downloadedPage('tghome_1', true, 471);
        self::assertSame(471, $this->storage->get(self::RUN_ID)['downloaded']);
        self::assertSame(1, $this->storage->get(self::RUN_ID)['pages']);
        $this->progress->startTree('next', 1, 1);
        self::assertSame(0, $this->storage->get(self::RUN_ID)['downloaded']);
        $this->progress->detach();
        $this->progress->downloadedPage('next', false, 700);
        self::assertSame(0, $this->storage->get(self::RUN_ID)['downloaded']);
    }

    public function testPauseKeepsCompletedStageAndRequiresExplicitResume(): void
    {
        $action = $this->createMock(CategorySynchronizationActionInterface::class);
        $calls = 0;
        $action->expects(self::exactly(2))->method('execute')->willReturnCallback(function () use (&$calls): array {
            $calls++;
            self::assertTrue($this->lock->isRunning());
            if ($calls === 1) {
                $this->progress->completeStage('tree', ['events' => 1, 'conflicts' => 0]);
                $this->storage->requestPause(self::RUN_ID);
            } else {
                self::assertSame(1, $this->progress->getStageResult('tree')['events']);
            }
            $this->progress->checkpoint('fetching_category_data', 1, 3, 'chairs');
            return ['events' => 2, 'conflicts' => 0];
        });
        $run = $this->runner($action);
        $paused = $run->execute(self::RUN_ID, 'all', 'sync');
        self::assertSame('paused', $paused['state']);
        self::assertSame(1, $paused['results']['tree']['events']);
        self::assertFalse($this->lock->isRunning());
        self::assertSame('paused', $run->execute(self::RUN_ID, 'all', 'sync')['state']);
        self::assertSame(1, $calls);
        $finished = $run->execute(self::RUN_ID, 'all', 'sync', true);
        self::assertSame('success', $finished['state']);
        self::assertSame('success', $run->execute(self::RUN_ID, 'all', 'sync')['state']);
        self::assertFalse($this->storage->isPauseRequested(self::RUN_ID));
        self::assertFalse($this->lock->isRunning());
    }

    public function testLostControlStateStopsBeforeTheNextWrite(): void
    {
        $writes = 0;
        $action = $this->createStub(CategorySynchronizationActionInterface::class);
        $action->method('execute')->willReturnCallback(function () use (&$writes): array {
            $this->cache = [];
            $this->progress->completedOperation('created');
            $this->progress->checkpoint('creating_category');
            $writes++;
            return ['events' => 1, 'conflicts' => 0];
        });
        $result = $this->runner($action)->execute(self::RUN_ID, 'tree', 'sync');
        self::assertSame('error', $result['state']);
        self::assertStringContainsString('expired', $result['message']);
        self::assertSame(0, $writes);
        self::assertFalse($this->lock->isRunning());
    }

    public function testMissingExpiredResumeNeverStartsAReplacementRun(): void
    {
        $action = $this->createMock(CategorySynchronizationActionInterface::class);
        $action->expects(self::never())->method('execute');
        $this->expectException(LocalizedException::class);
        $this->runner($action)->execute(self::RUN_ID, 'tree', 'sync', true);
    }

    public function testStatusDoesNotClaimAnAbandonedRunIsStillRunning(): void
    {
        $this->storage->save(self::RUN_ID, ['state' => 'running']);
        $run = $this->runner($this->createStub(CategorySynchronizationActionInterface::class));
        $result = $run->getStatus(self::RUN_ID);
        self::assertSame('error', $result['state']);
        self::assertStringContainsString('interrupted', $result['message']);
    }

    public function testPauseCanArriveBeforeTheExecutionRequest(): void
    {
        $action = $this->createMock(CategorySynchronizationActionInterface::class);
        $action->expects(self::never())->method('execute');
        $run = $this->runner($action);
        $run->pause(self::RUN_ID);
        self::assertSame('paused', $run->execute(self::RUN_ID, 'tree', 'sync')['state']);
    }

    public function testIdentifiersAreValidatedBeforeReadingSharedCache(): void
    {
        $this->expectException(LocalizedException::class);
        $this->storage->get('../other-cache-key');
    }

    public function testCommittedOperationCountsSurvivePauseResumeAndTreeChanges(): void
    {
        $action = $this->createStub(CategorySynchronizationActionInterface::class);
        $calls = 0;
        $action->method('execute')->willReturnCallback(function () use (&$calls): array {
            $this->progress->startTree('tree', 1, 2);
            $this->progress->completedOperation('created');
            if (++$calls === 1) {
                $this->storage->requestPause(self::RUN_ID);
                $this->progress->completedOperation('moved');
            }
            $this->progress->checkpoint('applying_tree');
            $this->progress->startTree('next', 2, 2);
            $this->progress->completedOperation('deleted');
            return ['events' => 1, 'conflicts' => 0];
        });
        $run = $this->runner($action);
        $paused = $run->execute(self::RUN_ID, 'tree', 'sync');
        self::assertSame('paused', $paused['state']);
        self::assertSame(['created' => 1, 'moved' => 1], $paused['operations']);
        $finished = $run->execute(self::RUN_ID, 'tree', 'sync', true);
        self::assertSame(['created' => 2, 'moved' => 1, 'deleted' => 1], $finished['operations']);
        $this->progress->completedOperation('created');
        self::assertSame($finished['operations'], $run->getStatus(self::RUN_ID)['operations']);
    }

    private function runner(CategorySynchronizationActionInterface $action): CategorySynchronizationRun
    {
        return new CategorySynchronizationRun(
            $this->storage,
            $this->progress,
            $this->lock,
            $action,
            new NullLogger(),
            new CategoryReconciliationErrorFormatter()
        );
    }
}
