<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Import;

use Ergonode\Core\Api\SynchronizationStatusProviderInterface;
use Ergonode\Core\Api\SynchronizationObservationProviderInterface;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Import\SynchronizationMonitor;
use PHPUnit\Framework\TestCase;

class SynchronizationMonitorTest extends TestCase
{
    public function testReturnsRegisteredCursorStateAndItsResetTime(): void
    {
        $process = [
            'process_code' => 'attributeStream',
            'label' => 'Attributes',
            'description' => 'Attribute synchronization',
            'cursor' => null,
            'synced_at' => '2026-09-05 10:01:00',
        ];
        $statuses = $this->createStub(SynchronizationStatusProviderInterface::class);
        $statuses->method('getList')->willReturn([$process]);
        $storage = $this->createMock(CursorStorage::class);
        $storage->expects(self::once())->method('getResetAt')
            ->with('attributeStream')->willReturn('2026-09-05 10:04:00');

        self::assertSame(
            [$process + ['reset_at' => '2026-09-05 10:04:00']],
            (new SynchronizationMonitor($statuses, $storage))->getList()
        );
    }

    public function testAddsObservationsOnlyForRegisteredProcesses(): void
    {
        $statuses = $this->createStub(SynchronizationStatusProviderInterface::class);
        $statuses->method('getList')->willReturn([['process_code' => 'check']]);
        $storage = $this->createStub(CursorStorage::class);
        $provider = $this->createMock(SynchronizationObservationProviderInterface::class);
        $observation = ['status' => 'no_changes', 'started_at' => '2026-09-11 10:00:00',
            'completed_at' => '2026-09-11 10:00:01', 'changed_at' => null];
        $provider->expects(self::once())->method('getStatus')->willReturn($observation);
        $orphan = $this->createMock(SynchronizationObservationProviderInterface::class);
        $orphan->expects(self::never())->method('getStatus');
        $monitor = new SynchronizationMonitor($statuses, $storage, ['check' => $provider, 'disabled' => $orphan]);
        self::assertSame($observation, $monitor->getList()[0]['observation']);
    }

    public function testDoesNotLoadCursorsWhenNoProcessIsRegistered(): void
    {
        $statuses = $this->createStub(SynchronizationStatusProviderInterface::class);
        $statuses->method('getList')->willReturn([]);
        $storage = $this->createMock(CursorStorage::class);
        $storage->expects(self::never())->method('getResetAt');

        self::assertSame([], (new SynchronizationMonitor($statuses, $storage))->getList());
    }
}
