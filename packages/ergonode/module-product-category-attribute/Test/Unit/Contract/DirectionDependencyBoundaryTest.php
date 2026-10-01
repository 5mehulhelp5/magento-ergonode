<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Test\Unit\Contract;

use JsonException;
use PHPUnit\Framework\TestCase;

class DirectionDependencyBoundaryTest extends TestCase
{
    /** @throws JsonException */
    public function testBaseModuleDoesNotRequireEitherProductDirection(): void
    {
        $root = dirname(__DIR__, 3);
        /** @var array{require: array<string, string>} $composer */
        $composer = json_decode(
            (string)file_get_contents($root . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $moduleConfig = (string)file_get_contents($root . '/etc/module.xml');
        $di = (string)file_get_contents($root . '/etc/di.xml');

        self::assertArrayHasKey('ergonode/module-product-attribute', $composer['require']);
        self::assertArrayNotHasKey('ergonode/module-product-attribute-consumer', $composer['require']);
        self::assertArrayNotHasKey('ergonode/module-category', $composer['require']);
        self::assertArrayNotHasKey('ergonode/module-product-consumer', $composer['require']);
        self::assertArrayNotHasKey('ergonode/module-product-publisher', $composer['require']);
        self::assertStringNotContainsString('Ergonode_Category', $moduleConfig);
        self::assertStringNotContainsString('Ergonode_ProductConsumer', $moduleConfig);
        self::assertStringNotContainsString('Ergonode_ProductPublisher', $moduleConfig);
        self::assertStringNotContainsString('Ergonode\ProductConsumer', $di);
        self::assertStringNotContainsString('Ergonode\ProductPublisher', $di);
    }
}
