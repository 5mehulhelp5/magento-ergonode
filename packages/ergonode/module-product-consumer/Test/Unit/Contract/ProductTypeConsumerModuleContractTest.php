<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class ProductTypeConsumerModuleContractTest extends TestCase
{
    public function testCoreModuleOwnsItsAvailableProductTypeAdapters(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode(file_get_contents($root . '/composer.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
        $di = file_get_contents($root . '/etc/di.xml') ?: '';

        self::assertSame('ergonode/module-product-consumer', $composer['name']);
        self::assertArrayNotHasKey('ergonode/module-media', $composer['require']);
        self::assertStringNotContainsString('Ergonode_Media', file_get_contents($root . '/etc/module.xml') ?: '');
        self::assertStringContainsString('<item name="simple" xsi:type="object">', $di);
        self::assertStringContainsString('<item name="virtual" xsi:type="object">', $di);
    }
}
