<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductAttribute\Model\ProductAttributePlacementPolicy;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use Ergonode\ProductAttribute\Model\Mapping\ProductAttributeMappingCompatibility;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductAttributeMappingCompatibilityTest extends TestCase
{
    #[DataProvider('compatibilityProvider')]
    public function testAppliesTargetSpecificConstraints(
        string $ergonodeType,
        string $magentoType,
        string $magentoCode,
        bool $expected
    ): void {
        $policy = $this->policy();
        $compatibility = new ProductAttributeMappingCompatibility(
            new AttributeTypeCompatibility(),
            $policy
        );

        self::assertSame(
            $expected,
            $compatibility->canMapAttributes($ergonodeType, $magentoType, $magentoCode)
        );
    }

    /**
     * @return array<string, array{string, string, string, bool}>
     */
    public static function compatibilityProvider(): array
    {
        return [
            'name accepts text' => ['text', 'text', 'name', true],
            'name rejects textarea' => ['textarea', 'text', 'name', false],
            'url key accepts text' => ['text', 'text', 'url_key', true],
            'url key rejects select' => ['select', 'text', 'url_key', false],
            'price accepts price' => ['price', 'price', 'price', true],
            'ordinary text keeps generic conversion' => ['select', 'text', 'manufacturer_label', true],
            'sku requires Ergonode text' => ['select', 'text', 'sku', false],
        ];
    }

    public function testExposesTheSameRulesToTheAdminUi(): void
    {
        $policy = $this->policy();
        $compatibility = new ProductAttributeMappingCompatibility(
            new AttributeTypeCompatibility(),
            $policy
        );

        self::assertContains('text', $compatibility->getAttributeCompatibilityMap()['select']);
        self::assertSame($policy->getErgonodeTypeConstraints(), $compatibility->getMagentoAttributeTypeConstraints());
    }

    private function policy(): ProductAttributePolicy
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);

        return new ProductAttributePolicy(
            $scopeConfig,
            new ProductAttributePlacementPolicy(),
            $this->createStub(ProductIdentityModeProviderInterface::class),
            $this->createStub(MagentoIdentityAttributeInterface::class)
        );
    }
}
