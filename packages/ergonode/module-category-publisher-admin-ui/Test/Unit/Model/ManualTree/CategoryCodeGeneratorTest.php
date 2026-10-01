<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCodeGenerator;
use JsonException;
use PHPUnit\Framework\TestCase;

class CategoryCodeGeneratorTest extends TestCase
{
    /** @throws JsonException */
    public function testGeneratesCodesFromSharedParityCases(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/Fixtures/category-code-cases.json');
        self::assertIsString($contents);
        $cases = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $generator = new CategoryCodeGenerator();

        foreach ($cases as $case) {
            self::assertSame($case['expected'], $generator->generate($case['labels']));
        }
    }

    public function testLimitsGeneratedCodeToErgonodeMaximumLength(): void
    {
        self::assertSame(128, strlen((new CategoryCodeGenerator())->generate([str_repeat('a', 150)])));
    }
}
