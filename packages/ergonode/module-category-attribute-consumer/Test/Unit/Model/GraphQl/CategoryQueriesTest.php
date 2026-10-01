<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\GraphQl;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\CategoryAttributeConsumer\Model\GraphQl\CategoryAttributeQueries;
use PHPUnit\Framework\TestCase;

class CategoryQueriesTest extends TestCase
{
    public function testCategoryEntityAliasesTranslationsForEveryAttributeValueType(): void
    {
        preg_match_all(
            '/\.\.\. on (\w+) \{\s+(\w+): translations\(/',
            CategoryAttributeQueries::CATEGORY_FIELDS,
            $matches,
            PREG_SET_ORDER
        );

        $aliases = [];
        foreach ($matches as $match) {
            $aliases[$match[1]] = $match[2];
        }

        self::assertSame(ErgonodeAttributeTypeInterface::TRANSLATION_KEYS, $aliases);
    }
}
