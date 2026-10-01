<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class ProductTypeModuleContractTest extends TestCase
{
    public function testCoreModuleOwnsItsAvailableProductTypeMappings(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode(file_get_contents($root . '/composer.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
        $di = file_get_contents($root . '/etc/di.xml') ?: '';

        self::assertSame('ergonode/module-product-publisher', $composer['name']);
        self::assertStringContainsString('<item name="simple" xsi:type="string">simple</item>', $di);
        self::assertStringContainsString('<item name="virtual" xsi:type="string">simple</item>', $di);
    }
}
