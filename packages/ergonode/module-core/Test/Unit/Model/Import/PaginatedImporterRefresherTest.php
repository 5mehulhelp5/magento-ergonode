<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Ergonode\Core\Model\Import\PaginatedImporterRefresher;

class PaginatedImporterRefresherTest extends TestCase
{
    public function testImportsEveryPageUntilCompletion(): void
    {
        $cursors = [];

        (new PaginatedImporterRefresher())->refresh(
            static function (?string $cursor) use (&$cursors): array {
                $cursors[] = $cursor;

                return $cursor === null
                    ? ['has_more' => true, 'cursor' => 'next']
                    : ['has_more' => false, 'cursor' => null];
            }
        );

        self::assertSame([null, 'next'], $cursors);
    }

    public function testRejectsPaginationThatDoesNotAdvance(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('pagination did not advance');

        (new PaginatedImporterRefresher())->refresh(
            static fn (?string $cursor): array => ['has_more' => true, 'cursor' => $cursor]
        );
    }
}
