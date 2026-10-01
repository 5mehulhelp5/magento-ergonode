<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\AutomaticAttributeMappingPolicy;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AutomaticAttributeMappingPolicyTest extends TestCase
{
    #[DataProvider('excludedAttributeCodeProvider')]
    public function testRejectsSystemAttributeCodes(string $attributeCode): void
    {
        $policy = new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class));
        self::assertFalse($policy->isAllowed($attributeCode));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function excludedAttributeCodeProvider(): array
    {
        return [
            'gallery' => ['gallery'],
            'stock flag' => ['is_in_stock'],
            'media gallery' => ['media_gallery'],
            'price' => ['price'],
            'quantity' => ['qty'],
            'Magento stock attribute' => ['quantity_and_stock_status'],
            'special price' => ['special_price'],
            'status' => ['status'],
            'stock' => ['stock'],
            'stock status' => ['stock_status'],
            'URL key normalization' => [' URL_KEY '],
            'visibility' => ['visibility'],
        ];
    }

    public function testAllowsCustomAttributeCode(): void
    {
        $policy = new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class));
        self::assertTrue($policy->isAllowed('manufacturer'));
    }
}
