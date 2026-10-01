<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\CursorPaginationGuardInterface;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuard;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CursorPaginationGuardTest extends TestCase
{
    public function testAcceptsAdvancingPaginationUntilCompletion(): void
    {
        $guard = new CursorPaginationGuard('test connection');

        self::assertSame('first', $guard->next(['hasNextPage' => true, 'endCursor' => 'first']));
        self::assertSame('second', $guard->next(['hasNextPage' => true, 'endCursor' => 'second']));
        self::assertNull($guard->next(['hasNextPage' => false]));
    }

    public function testRejectsLongerCursorCycle(): void
    {
        $guard = new CursorPaginationGuard('test connection');
        $guard->next(['hasNextPage' => true, 'endCursor' => 'first']);
        $guard->next(['hasNextPage' => true, 'endCursor' => 'second']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('invalid pagination or a cyclic cursor');

        $guard->next(['hasNextPage' => true, 'endCursor' => 'first']);
    }

    public function testRejectsSequenceBeyondMaximumPageCount(): void
    {
        $guard = new CursorPaginationGuard('test connection');
        for ($page = 1; $page < CursorPaginationGuardInterface::MAX_PAGES; ++$page) {
            self::assertSame((string)$page, $guard->next([
                'hasNextPage' => true,
                'endCursor' => (string)$page,
            ]));
        }

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('100-page limit');

        $guard->next([
            'hasNextPage' => true,
            'endCursor' => (string)CursorPaginationGuardInterface::MAX_PAGES,
        ]);
    }
}
