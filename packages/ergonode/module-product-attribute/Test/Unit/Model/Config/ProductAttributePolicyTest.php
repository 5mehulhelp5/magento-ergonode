<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Config;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductAttribute\Model\ProductAttributePlacementPolicy;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ProductAttributePolicyTest extends TestCase
{
    public function testConfiguredModesControlMappingAvailabilityAndManualValues(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static function (string $path): string {
            self::assertStringStartsWith('ergonode_products/', $path);

            return match (true) {
                str_ends_with($path, '/price_mode') => 'manual',
                str_ends_with($path, '/price_default') => '12.50',
                str_ends_with($path, '/status') => 'manual',
                str_ends_with($path, '/status_default') => '1',
                str_ends_with($path, '/visibility') => 'manual',
                str_ends_with($path, '/visibility_default') => '4',
                str_ends_with($path, '/url_key') => 'mapping',
                default => '',
            };
        });

        $identityMode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $identityMode->method('getMode')->willReturn('assigned');
        $policy = new ProductAttributePolicy(
            $scopeConfig,
            new ProductAttributePlacementPolicy(),
            $identityMode,
            $this->createStub(MagentoIdentityAttributeInterface::class)
        );

        self::assertTrue($policy->isMappable('sku'));
        self::assertTrue($policy->isMappable('url_key'));
        self::assertTrue($policy->isMappingRequired('sku'));
        self::assertTrue($policy->isMappingRequired('url_key'));
        self::assertFalse($policy->isMappable('price'));
        self::assertFalse($policy->isMappable('status'));
        self::assertFalse($policy->isMappable('visibility'));
        self::assertFalse($policy->isMappingRequired('price'));
        self::assertFalse($policy->isMappingRequired('status'));
        self::assertFalse($policy->isMappingRequired('visibility'));
        self::assertFalse($policy->isMappingRequired('name'));
        self::assertFalse($policy->isMappable('gallery'));
        self::assertFalse($policy->isMappingRequired('gallery'));
        self::assertFalse($policy->isMappable('media_gallery'));
        self::assertFalse($policy->isErgonodeMappable(' gallery '));
        self::assertFalse($policy->isErgonodeMappable('GALLERY'));
        self::assertTrue($policy->isErgonodeMappable('color'));
        self::assertFalse($policy->isMappable('image'));
        self::assertFalse($policy->isMappable('weight_type'));
        self::assertTrue($policy->isTemplatePlacementProtected('sku'));
        self::assertTrue($policy->isTemplatePlacementProtected('url_key'));
        self::assertTrue($policy->isTemplatePlacementProtected('price'));
        self::assertTrue($policy->isTemplatePlacementProtected('status'));
        self::assertTrue($policy->isTemplatePlacementProtected('visibility'));
        self::assertTrue($policy->isTemplatePlacementProtected('special_price'));
        self::assertTrue($policy->isTemplatePlacementProtected('quantity_and_stock_status'));
        self::assertFalse($policy->isTemplatePlacementProtected('color'));
        self::assertSame(['text'], $policy->getAllowedErgonodeTypes('name'));
        self::assertSame(['text'], $policy->getAllowedErgonodeTypes(' URL_KEY '));
        self::assertSame(['price'], $policy->getAllowedErgonodeTypes('price'));
        self::assertSame(['text'], $policy->getAllowedErgonodeTypes('sku'));
        self::assertSame([
            'name' => ['text'],
            'sku' => ['text'],
            'url_key' => ['text'],
            'price' => ['price'],
        ], $policy->getErgonodeTypeConstraints());
        self::assertSame([
            'price' => '12.50',
            'status' => 1,
            'visibility' => 4,
        ], $policy->getManualCreationValues());
    }

    public function testUnknownModesUseSafeDefaults(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('unsupported');

        $identityMode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $identityMode->method('getMode')->willReturn('shared');
        $policy = new ProductAttributePolicy(
            $scopeConfig,
            new ProductAttributePlacementPolicy(),
            $identityMode,
            $this->createStub(MagentoIdentityAttributeInterface::class)
        );

        self::assertFalse($policy->isMappable('sku'));
        self::assertFalse($policy->isMappable('url_key'));
        self::assertTrue($policy->isMappable('price'));
        self::assertTrue($policy->isMappable('status'));
        self::assertTrue($policy->isMappable('visibility'));
        self::assertFalse($policy->isMappingRequired('sku'));
        self::assertFalse($policy->isMappingRequired('url_key'));
        self::assertTrue($policy->isMappingRequired('price'));
        self::assertTrue($policy->isMappingRequired('status'));
        self::assertTrue($policy->isMappingRequired('visibility'));
        self::assertSame([], $policy->getManualCreationValues());
    }
}
