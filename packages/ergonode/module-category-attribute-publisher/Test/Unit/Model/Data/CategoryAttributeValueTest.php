<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Model\Data;

use Ergonode\CategoryAttributePublisher\Model\Data\CategoryAttributeValue;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryAttributeValueTest extends TestCase
{
    public function testValuesAreNormalizedBySchemaType(): void
    {
        self::assertSame(
            ['en_GB' => 2.0, 'pl_PL' => 1.5],
            (new CategoryAttributeValue('weight', 'unit', ['pl_PL' => 1.5, 'en_GB' => 2]))->getTranslations()
        );
        self::assertSame(
            ['pl_PL' => ['red', 'blue']],
            (new CategoryAttributeValue('color', 'multi_select', ['pl_PL' => ['red', 'blue']]))
                ->getTranslations()
        );
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRejected(string $type, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CategoryAttributeValue('attribute', $type, ['pl_PL' => $value]);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidValues(): array
    {
        return [
            'unsupported type' => ['unknown', 'value'],
            'numeric string' => ['numeric', '1.5'],
            'text array' => ['text', ['value']],
            'select integer' => ['select', 1],
            'relation associative array' => ['product_relation', ['sku' => 'SKU-1']],
            'gallery empty path' => ['gallery', ['']],
        ];
    }
}
