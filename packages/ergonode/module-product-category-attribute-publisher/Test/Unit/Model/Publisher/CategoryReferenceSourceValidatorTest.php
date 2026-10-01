<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributePublisher\Test\Unit\Model\Publisher;

use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttributePublisher\Model\Publisher\CategoryReferenceSourceValidator;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryReferenceSourceValidatorTest extends TestCase
{
    /** @param array<string, mixed> $sourceValues */
    #[DataProvider('unassignedValues')]
    public function testRejectsUnassignedIdEvenWhenErgonodeCodeMatches(array $sourceValues): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Default category "chairs" is not assigned');
        $this->validator()->validate(
            $this->product(),
            ['magento_attribute_code' => 'default_category'],
            ['en_GB' => 'chairs', 'pl_PL' => 'chairs'],
            $sourceValues
        );
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unassignedValues(): array
    {
        return [
            'different ID with the same code' => [['en_GB' => 42, 'pl_PL' => 87]],
            'store override is unassigned' => [['en_GB' => 87, 'pl_PL' => 42]],
            'missing original value' => [['en_GB' => 87]],
        ];
    }

    public function testAcceptsAssignedStoreValues(): void
    {
        $this->validator()->validate(
            $this->product(),
            ['magento_attribute_code' => 'default_category'],
            ['en_GB' => 'chairs', 'pl_PL' => 'chairs'],
            ['en_GB' => '87', 'pl_PL' => 87]
        );
        self::assertTrue(true);
    }

    public function testOptionalClearedValueDoesNotRequireAnAssignedCategory(): void
    {
        $this->validator()->validate($this->product(), ['magento_attribute_code' => 'default_category'], [], []);
        self::assertTrue(true);
    }

    public function testMagentoRequiredFlagDoesNotDecidePublicationRequirements(): void
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturn(true);
        $config->method('isRequired')->willReturn(true);

        (new CategoryReferenceSourceValidator($config))->validate(
            $this->product(),
            ['magento_attribute_code' => 'default_category'],
            [],
            []
        );
        self::assertTrue(true);
    }

    private function validator(): CategoryReferenceSourceValidator
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturn(true);

        return new CategoryReferenceSourceValidator($config);
    }

    private function product(): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getCategoryIds')->willReturn([87]);

        return $product;
    }
}
