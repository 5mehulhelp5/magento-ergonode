<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Provider;

use Ergonode\CategoryConsumer\Model\Provider\CategoryTreeSyncMetadataProvider;
use Ergonode\Core\Model\Import\CursorStorage;
use PHPUnit\Framework\TestCase;

class CategoryTreeSyncMetadataProviderTest extends TestCase
{
    public function testReturnsGlobalTreeStreamCursorMetadata(): void
    {
        $cursorStorage = $this->createMock(CursorStorage::class);
        $cursorStorage->expects(self::once())
            ->method('get')
            ->with('category_tree_stream')
            ->willReturn([
                'cursor' => 'cursor-2',
                'synced_at' => '2026-08-11 10:42:17',
            ]);

        self::assertSame(
            ['cursor' => 'cursor-2', 'synced_at' => '2026-08-11 10:42:17'],
            (new CategoryTreeSyncMetadataProvider($cursorStorage))->get()
        );
    }
}
