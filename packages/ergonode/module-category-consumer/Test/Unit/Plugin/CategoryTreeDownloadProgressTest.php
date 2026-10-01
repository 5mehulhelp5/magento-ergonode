<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Plugin;

use Ergonode\Category\Model\Import\CategoryTreePageReader;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\CategoryConsumer\Plugin\CategoryTreeDownloadProgress;
use PHPUnit\Framework\TestCase;

class CategoryTreeDownloadProgressTest extends TestCase
{
    public function testReportsActualNodesAndPreservesTheTransportResult(): void
    {
        $result = ['data' => ['categoryTree' => ['categoryTreeLeafList' => ['edges' => [
            ['node' => ['code' => 'a']], ['node' => ['code' => 'b']], ['node' => null], null,
        ]]]], 'seconds' => 0.2];
        $progress = $this->createMock(CategorySynchronizationProgress::class);
        $progress->expects(self::once())->method('downloadedPage')->with('home', true, 2);
        $plugin = new CategoryTreeDownloadProgress($progress);

        self::assertSame($result, $plugin->afterRead(
            $this->createStub(CategoryTreePageReader::class),
            $result,
            'home',
            null
        ));
    }
}
