<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\GraphQl;

use Ergonode\ProductConsumer\Model\GraphQl\PaginationCursorGuard;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class PaginationCursorGuardTest extends TestCase
{
    public function testStopsAtCompleteConnection(): void
    {
        $guard = new PaginationCursorGuard('test');

        self::assertNull($guard->next(['pageInfo' => ['hasNextPage' => false, 'endCursor' => 'last']]));
    }

    public function testRejectsCursorCycle(): void
    {
        $guard = new PaginationCursorGuard('test');
        self::assertSame('one', $guard->next([
            'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'one'],
        ]));
        self::assertSame('two', $guard->next([
            'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'two'],
        ]));

        $this->expectException(LocalizedException::class);
        $guard->next(['pageInfo' => ['hasNextPage' => true, 'endCursor' => 'one']]);
    }
}
