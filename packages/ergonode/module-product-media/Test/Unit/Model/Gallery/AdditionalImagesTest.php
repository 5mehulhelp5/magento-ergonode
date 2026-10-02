<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Gallery;

use Ergonode\ProductMedia\Model\Gallery\AdditionalImages;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AdditionalImagesTest extends TestCase
{
    public function testInsertMoveAndKeepPrimaryWithoutCopying(): void
    {
        self::assertSame(['a', 'c', 'b', 'd'], (new AdditionalImages())->arrange(['a', 'b', 'c'], ['c' => 2,
            'd' => 4]));
        self::assertSame(['a', 'b'], (new AdditionalImages())->arrange(['a', 'b'], ['a' => 2]));
    }
    public function testOversizedPositionAppends(): void
    {
        self::assertSame(['a', 'b'], (new AdditionalImages())->arrange(['a'], ['b' => 20]));
    }
    public function testRejectsPositionCollision(): void
    {
        $this->expectException(LocalizedException::class);
        (new AdditionalImages())->arrange(['a'], ['b' => 2, 'c' => 2]);
    }
}
