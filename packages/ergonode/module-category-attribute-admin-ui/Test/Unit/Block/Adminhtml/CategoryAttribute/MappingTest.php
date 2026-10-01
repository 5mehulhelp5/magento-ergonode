<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryAttribute\Test\Unit;

use Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryAttribute\Mapping;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MappingTest extends TestCase
{
    public function testCompatibilityUsesTheCategoryMappingProviderResult(): void
    {
        $block = (new ReflectionClass(Mapping::class))->newInstanceWithoutConstructor();

        self::assertTrue($block->isTypeCompatible(['tone' => 'ok']));
        self::assertFalse($block->isTypeCompatible(['tone' => 'error']));
        self::assertTrue($block->isTypeCompatible(['tone' => 'warning', 'left' => null]));
        self::assertTrue($block->isTypeCompatible([]));
    }
}
