<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Test\Unit\Contract;

use JsonException;
use PHPUnit\Framework\TestCase;

class ModuleBoundaryTest extends TestCase
{
    /** @throws JsonException */
    public function testCategoryPublisherDoesNotRequireCategoryAttributeFeatures(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        /** @var array{require: array<string, string>} $composer */
        $composer = json_decode(
            (string)file_get_contents($moduleRoot . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $moduleConfig = (string)file_get_contents($moduleRoot . '/etc/module.xml');

        foreach (array_keys($composer['require']) as $dependency) {
            self::assertStringNotContainsString('category-attribute', $dependency);
        }
        self::assertStringNotContainsString('Ergonode_CategoryAttribute', $moduleConfig);
    }
}
