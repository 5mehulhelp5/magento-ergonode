<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributePublisher\Test\Unit\Contract;

use JsonException;
use PHPUnit\Framework\TestCase;

class DirectionDependencyBoundaryTest extends TestCase
{
    /** @throws JsonException */
    public function testPublisherAdapterOwnsOnlyOutboundWiring(): void
    {
        $root = dirname(__DIR__, 3);
        /** @var array{require: array<string, string>} $composer */
        $composer = json_decode(
            (string)file_get_contents($root . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $di = (string)file_get_contents($root . '/etc/di.xml');

        self::assertArrayHasKey('ergonode/module-category', $composer['require']);
        self::assertArrayHasKey('ergonode/module-product-category-attribute', $composer['require']);
        self::assertArrayHasKey('ergonode/module-product-publisher', $composer['require']);
        self::assertArrayNotHasKey('ergonode/module-product-consumer', $composer['require']);
        self::assertStringContainsString('Ergonode\ProductPublisher', $di);
        self::assertStringNotContainsString('Ergonode\ProductConsumer', $di);
    }
}
