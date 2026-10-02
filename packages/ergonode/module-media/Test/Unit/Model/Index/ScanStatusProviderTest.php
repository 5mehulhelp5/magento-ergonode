<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Index;

use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Index\ScanStatusProvider;
use Ergonode\Media\Model\Port\ScanStateInterface;
use PHPUnit\Framework\TestCase;

class ScanStatusProviderTest extends TestCase
{
    public function testReachingTheDatabaseEstimateNeverShowsCompletion(): void
    {
        $state = $this->createStub(ScanStateInterface::class);
        $state->method('read')->willReturn([
            'status' => 'running', 'estimated_total' => 10, 'indexed' => 15, 'reused' => 0,
            'started_at' => time() - 20, 'updated_at' => time(),
        ]);
        $readiness = $this->createStub(ScanReadiness::class);
        $readiness->method('isBlocked')->willReturn(true);
        $result = (new ScanStatusProvider($state, $readiness))->getStatus();
        self::assertSame(99, $result['percent']);
        self::assertNull($result['estimated_remaining_seconds']);
        self::assertTrue($result['blocked']);
    }

    public function testEmptyScanShowsOneHundredPercentOnlyAfterCompletion(): void
    {
        $state = $this->createStub(ScanStateInterface::class);
        $state->method('read')->willReturn([
            'status' => 'complete', 'estimated_total' => 0, 'indexed' => 0, 'reused' => 0,
            'started_at' => 100, 'updated_at' => 120,
        ]);
        $result = (new ScanStatusProvider($state, $this->createStub(ScanReadiness::class)))->getStatus();
        self::assertSame(100, $result['percent']);
        self::assertSame(20, $result['elapsed_seconds']);
    }
}
