<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\Product\Model\Config\ProductIdentityModeProvider;
use Ergonode\ProductAttributeConsumer\Model\Product\SkuIdentityMappingResolver;
use Ergonode\ProductConsumer\Model\Magento\MappedMagentoSkuResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class MappedMagentoSkuResolverTest extends TestCase
{
    public function testImportRequiresExplicitNewBindingMode(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->expects(self::once())->method('getValue')
            ->with('ergonode_products/identity/sku_mode')->willReturn('shared');
        $resolver = new MappedMagentoSkuResolver(
            new ProductIdentityModeProvider($config),
            $this->createStub(SkuIdentityMappingResolver::class)
        );

        $this->expectException(LocalizedException::class);
        $resolver->getConfiguredMode();
    }

    public function testImportDoesNotSilentlyUseSharedSkuWhenAssignedSupportIsRemoved(): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('assigned');
        $resolver = new MappedMagentoSkuResolver(
            new ProductIdentityModeProvider($config),
            $this->createStub(SkuIdentityMappingResolver::class)
        );

        $this->expectException(LocalizedException::class);
        $resolver->getConfiguredMode();
    }
}
