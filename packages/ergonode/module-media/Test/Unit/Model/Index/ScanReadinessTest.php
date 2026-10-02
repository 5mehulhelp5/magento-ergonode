<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Index;

use Ergonode\Media\Model\Config\GalleryModeProvider;
use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Port\ScanStateInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScanReadinessTest extends TestCase
{
    #[DataProvider('states')]
    public function testOnlyACompletedScanUnlocksSharedMode(string $mode, ?int $completed, bool $blocked): void
    {
        $state = $this->createStub(ScanStateInterface::class);
        $state->method('read')->willReturn(['last_completed_at' => $completed]);
        $provider = $this->createStub(GalleryModeProvider::class);
        $provider->method('get')->willReturn($mode);
        self::assertSame($blocked, (new ScanReadiness($state, $provider))->isBlocked());
    }

    public static function states(): array
    {
        return [['shared', null, true], ['shared', 123, false], ['seo', null, false]];
    }
}
