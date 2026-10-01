<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Unit\Model\Config;

use Ergonode\ProductAttributePublisher\Model\Config\AttributeSourceConfiguration;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeSourceConfigurationTest extends TestCase
{
    public function testReadsMagentoCurrency(): void
    {
        $values = [
            'currency/options/base' => 'EUR',
        ];
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $values[$path] ?? null
        );
        $configuration = new AttributeSourceConfiguration($scopeConfig);

        self::assertSame('EUR', $configuration->getPriceCurrency());
    }

    public function testRejectsMissingMagentoCurrency(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $configuration = new AttributeSourceConfiguration($scopeConfig);

        $this->expectException(LocalizedException::class);
        $configuration->getPriceCurrency();
    }
}
