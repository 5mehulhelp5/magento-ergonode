<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Contract;

use JsonException;
use PHPUnit\Framework\TestCase;

class ProductModuleBoundaryTest extends TestCase
{
    /** @throws JsonException */
    public function testAttributeConsumerDoesNotDependOnProductModule(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $composer = json_decode(
            (string)file_get_contents($moduleRoot . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertArrayNotHasKey('ergonode/module-product', $composer['require'] ?? []);
        self::assertStringNotContainsString(
            'Ergonode_Product',
            (string)file_get_contents($moduleRoot . '/etc/module.xml')
        );
    }
}
