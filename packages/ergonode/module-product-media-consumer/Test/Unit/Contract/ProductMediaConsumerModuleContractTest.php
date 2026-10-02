<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class ProductMediaConsumerModuleContractTest extends TestCase
{
    public function testBridgeOwnsAllProductMediaConsumerWiring(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode(file_get_contents($root . '/composer.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
        $di = file_get_contents($root . '/etc/di.xml') ?: '';

        self::assertSame('ergonode/module-product-media-consumer', $composer['name']);
        self::assertArrayHasKey('ergonode/module-product-consumer', $composer['require']);
        self::assertArrayHasKey('ergonode/module-media', $composer['require']);
        self::assertStringContainsString('ProductAttributeCodeProviderPool', $di);
        self::assertStringContainsString('ProductAttributeMappingDeferrerPool', $di);
        self::assertStringContainsString('ProductStateSynchronizerPool', $di);
    }
}
