<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model;

use Ergonode\ProductPublisher\Model\ProductTypeMapper;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProductTypeMapperTest extends TestCase
{
    public function testMapsSupportedMagentoTypes(): void
    {
        $mapper = new ProductTypeMapper();

        self::assertSame('simple', $mapper->map('simple'));
        self::assertSame('simple', $mapper->map('virtual'));
        self::assertSame('variable', (new ProductTypeMapper(['configurable' => 'variable']))->map('configurable'));
        self::assertSame('grouping', (new ProductTypeMapper(['grouped' => 'grouping']))->map('grouped'));
    }

    public function testRejectsUnsupportedMagentoType(): void
    {
        $this->expectException(LocalizedException::class);
        (new ProductTypeMapper())->map('custom');
    }
}
