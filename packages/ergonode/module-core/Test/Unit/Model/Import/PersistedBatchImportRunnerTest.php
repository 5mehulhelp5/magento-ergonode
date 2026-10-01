<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Import;

use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Import\PersistedBatchImportRunner;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class PersistedBatchImportRunnerTest extends TestCase
{
    public function testUsesPersistedCursorAndConfiguredPageSize(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->expects($this->once())->method('get')->with('templates')->willReturn([
            'cursor' => 'current',
        ]);
        $storage->expects($this->once())->method('save')->with('templates', 'next');
        $result = $this->batch(true, 'next', 25, 2, 1, 1);

        $actual = $this->runner($storage)->executeBatch(
            'templates',
            50,
            null,
            static function (?string $cursor, int $pageSize) use ($result): array {
                self::assertSame('current', $cursor);
                self::assertSame(50, $pageSize);
                return $result;
            }
        );

        self::assertSame($result, $actual);
    }

    public function testSavesTerminalCursorWhenImportCompletes(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->method('get')->willReturn(null);
        $storage->expects($this->once())->method('save')->with('attributes', 'terminal');
        $storage->expects($this->never())->method('reset');
        $result = $this->batch(false, 'terminal', 200, 1, 1, 0);

        self::assertSame(
            $result,
            $this->runner($storage)->executeBatch(
                'attributes',
                200,
                null,
                static fn (?string $cursor, int $pageSize): array => $result
            )
        );
    }

    public function testPreservesPersistedCursorWhenCompletedResponseHasNoCursor(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->method('get')->willReturn(['cursor' => 'current']);
        $storage->expects($this->once())->method('save')->with('attributes', 'current');
        $storage->expects($this->never())->method('reset');
        $result = $this->batch(false, null, 50, 0, 0, 0);

        self::assertSame(
            $result,
            $this->runner($storage)->executeBatch(
                'attributes',
                200,
                null,
                static function (?string $cursor, int $pageSize) use ($result): array {
                    self::assertSame('current', $cursor);
                    self::assertSame(200, $pageSize);

                    return $result;
                }
            )
        );
    }

    public function testDoesNotCreateCursorStateWhenEmptyInitialImportCompletes(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->method('get')->willReturn(null);
        $storage->expects($this->never())->method('save');
        $storage->expects($this->never())->method('reset');
        $result = $this->batch(false, null, 200, 0, 0, 0);

        self::assertSame(
            $result,
            $this->runner($storage)->executeBatch(
                'attributes',
                200,
                null,
                static fn (?string $cursor, int $pageSize): array => $result
            )
        );
    }

    public function testRejectsNonAdvancingCursor(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->method('get')->willReturn(['cursor' => 'same']);
        $storage->expects($this->never())->method('save');
        $storage->expects($this->never())->method('reset');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('did not advance its cursor');

        $this->runner($storage)->executeBatch(
            'templates',
            50,
            null,
            fn (?string $cursor, int $pageSize): array => $this->batch(true, 'same', 50, 1, 1, 0)
        );
    }

    public function testAggregatesBatchesWithoutReenteringPublicProcess(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->expects($this->exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            null,
            ['cursor' => 'next']
        );
        $saveCalls = [];
        $storage->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(static function (...$arguments) use (&$saveCalls): void {
                $saveCalls[] = $arguments;
            });
        $storage->expects($this->never())->method('reset');
        $calls = 0;

        $summary = $this->runner($storage)->executeUntilComplete(
            'templates',
            50,
            null,
            10,
            function (?string $cursor, int $pageSize) use (&$calls): array {
                $calls++;
                return $calls === 1
                    ? $this->batch(true, 'next', $pageSize, 2, 1, 1)
                    : $this->batch(false, 'terminal', $pageSize, 1, 1, 0);
            }
        );

        self::assertSame(
            ['batches' => 2, 'imported' => 3, 'changed' => 2, 'unchanged' => 1, 'has_more' => false],
            $summary
        );
        self::assertSame([
            ['templates', 'next'],
            ['templates', 'terminal'],
        ], $saveCalls);
    }

    public function testRejectsConcurrentExecutionBeforeReadingCursor(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->expects(self::never())->method('get');
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())
            ->method('lock')
            ->with(self::callback(
                static fn (string $lockName): bool => str_starts_with($lockName, 'ergonode_import_')
            ), 0)
            ->willReturn(false);
        $lockManager->expects(self::never())->method('unlock');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('is already running');

        $this->runner($storage, $lockManager)->executeBatch(
            'attributes',
            200,
            null,
            static fn (?string $cursor, int $pageSize): array => []
        );
    }

    public function testReleasesProcessLockWhenImportFails(): void
    {
        $storage = $this->createStub(CursorStorage::class);
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())->method('lock')->willReturn(true);
        $lockManager->expects(self::once())->method('unlock');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('import failed');

        $this->runner($storage, $lockManager)->executeBatch(
            'attributes',
            200,
            null,
            static function (?string $cursor, int $pageSize): array {
                throw new LocalizedException(__('import failed'));
            }
        );
    }

    private function runner(
        CursorStorage $storage,
        ?LockManagerInterface $lockManager = null
    ): PersistedBatchImportRunner {
        if ($lockManager === null) {
            $lockManager = $this->createStub(LockManagerInterface::class);
            $lockManager->method('lock')->willReturn(true);
        }

        return new PersistedBatchImportRunner($storage, $lockManager);
    }

    /**
     * @return array{has_more: bool, cursor: string|null, page_size: int, imported: int, changed: int, unchanged: int}
     */
    private function batch(
        bool $hasMore,
        ?string $cursor,
        int $pageSize,
        int $imported,
        int $changed,
        int $unchanged
    ): array {
        return [
            'has_more' => $hasMore,
            'cursor' => $cursor,
            'page_size' => $pageSize,
            'imported' => $imported,
            'changed' => $changed,
            'unchanged' => $unchanged,
        ];
    }
}
