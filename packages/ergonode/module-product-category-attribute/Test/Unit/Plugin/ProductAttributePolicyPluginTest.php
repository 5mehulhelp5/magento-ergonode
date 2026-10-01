<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Test\Unit\Plugin;

use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttribute\Plugin\ProductAttributePlacementPolicyPlugin;
use Ergonode\ProductCategoryAttribute\Plugin\ProductAttributePolicyPlugin;
use PHPUnit\Framework\TestCase;

class ProductAttributePolicyPluginTest extends TestCase
{
    public function testConfiguredAttributeIsProtectedAndRestrictedToText(): void
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('getAttributeCode')->willReturn('default_category');
        $config->method('isConfigured')->willReturnCallback(
            static fn (string $code): bool => $code === 'default_category'
        );
        $policy = $this->createStub(ProductAttributePolicy::class);

        self::assertSame(
            ['text'],
            (new ProductAttributePolicyPlugin($config))->afterGetAllowedErgonodeTypes(
                $policy,
                null,
                'default_category'
            )
        );
        self::assertSame(
            ['default_category' => ['text']],
            (new ProductAttributePolicyPlugin($config))->afterGetErgonodeTypeConstraints($policy, [])
        );
        self::assertTrue((new ProductAttributePlacementPolicyPlugin($config))->afterIsProtected(
            $this->createStub(ProductAttributePlacementPolicyInterface::class),
            false,
            'default_category'
        ));
    }
}
