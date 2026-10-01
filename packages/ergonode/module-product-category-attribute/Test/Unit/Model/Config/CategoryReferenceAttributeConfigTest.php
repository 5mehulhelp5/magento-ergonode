<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Test\Unit\Model\Config;

use Ergonode\ProductCategoryAttribute\Model\Config\CategoryReferenceAttributeConfig;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class CategoryReferenceAttributeConfigTest extends TestCase
{
    public function testEmptyConfigurationDisablesCategoryReferenceMapping(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects(self::never())->method('getAttribute');

        $config = new CategoryReferenceAttributeConfig($scopeConfig, $eavConfig);

        self::assertSame('', $config->getAttributeCode());
        self::assertFalse($config->isConfigured('default_category'));
        self::assertFalse($config->isRequired());
    }

    public function testRecognizesRequiredConfiguredAttribute(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(' default_category ');
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(17);
        $attribute->method('getIsRequired')->willReturn(true);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects(self::once())
            ->method('getAttribute')
            ->with('catalog_product', 'default_category')
            ->willReturn($attribute);

        $config = new CategoryReferenceAttributeConfig($scopeConfig, $eavConfig);

        self::assertSame('default_category', $config->getAttributeCode());
        self::assertTrue($config->isConfigured('default_category'));
        self::assertFalse($config->isConfigured('manufacturer'));
        self::assertTrue($config->isRequired());
    }
}
