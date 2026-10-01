<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Unit\Model\Config;

use Ergonode\Product\Api\AssignedIdentitySupportInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Model\Config\ProductIdentityModeProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProductIdentityModeProviderTest extends TestCase
{
    public function testNewBindingsRequireExplicitModeButHistoricalSharedRemainsReadable(): void
    {
        $provider = new ProductIdentityModeProvider($this->createStub(ScopeConfigInterface::class));

        self::assertSame('', $provider->getMode());
        self::assertFalse($provider->isAssignedModeAvailable());
        $provider->assertModeAvailable('shared');
        $this->expectException(LocalizedException::class);
        $provider->assertNewModeAvailable('shared');
    }

    public function testRemovingSupportDoesNotSilentlyChangeAssignedConfiguration(): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('assigned');
        $provider = new ProductIdentityModeProvider($config);

        self::assertSame('assigned', $provider->getMode());
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('existing identities are preserved');
        $provider->assertModeAvailable('assigned');
    }

    public function testConfigurationCanSelectAssignedModeBeforeMappingIsCreated(): void
    {
        $support = $this->createMock(AssignedIdentitySupportInterface::class);
        $support->expects(self::never())->method('validate');
        $provider = new ProductIdentityModeProvider(
            $this->createStub(ScopeConfigInterface::class),
            [$support]
        );

        $provider->assertModeSupported('assigned');
    }

    public function testAssignedModeValidatesTheExtensionMapping(): void
    {
        $support = $this->createMock(AssignedIdentitySupportInterface::class);
        $support->expects(self::once())->method('validate');
        $provider = new ProductIdentityModeProvider(
            $this->createStub(ScopeConfigInterface::class),
            [$support]
        );

        self::assertTrue($provider->isAssignedModeAvailable());
        $provider->assertModeAvailable('assigned');
    }

    public function testInvalidConfigurationCannotFallBackToSharedIdentity(): void
    {
        $provider = new ProductIdentityModeProvider($this->createStub(ScopeConfigInterface::class));

        $this->expectException(LocalizedException::class);
        $provider->assertModeAvailable('invalid');
    }

    public function testMappedModeRequiresValidConfiguredMagentoAttribute(): void
    {
        $attribute = $this->createMock(MagentoIdentityAttributeInterface::class);
        $attribute->expects(self::once())->method('validate');
        $provider = new ProductIdentityModeProvider(
            $this->createStub(ScopeConfigInterface::class),
            [],
            $attribute
        );

        $provider->assertModeSupported('mapped');
        $provider->assertModeAvailable('mapped');
    }
}
