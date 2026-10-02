<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Materialization;

use Ergonode\Media\Model\Materialization\DefaultSharedPathStrategy;
use PHPUnit\Framework\TestCase;

class DefaultSharedPathStrategyTest extends TestCase
{
    public function testDifferentSourcePathsWithSameContentResolveToOneFile(): void
    {
        $path = (new DefaultSharedPathStrategy())->resolve(
            '/manuals/chair.pdf',
            hash('sha256', 'content', true),
            'pdf',
            'catalog/product/ergonode/shared'
        );

        self::assertStringStartsWith('catalog/product/ergonode/shared/', $path);
        self::assertStringEndsWith(hash('sha256', 'content') . '.pdf', $path);
        self::assertSame($path, (new DefaultSharedPathStrategy())->resolve(
            '/other/name.pdf',
            hash('sha256', 'content', true),
            'pdf',
            'catalog/product/ergonode/shared'
        ));
    }
}
