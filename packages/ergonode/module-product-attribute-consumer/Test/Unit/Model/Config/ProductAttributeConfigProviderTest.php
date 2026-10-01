<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Config;

use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ProductAttributeConfigProviderTest extends TestCase
{
    public function testReadsAttributeAutomationFlags(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::exactly(4))
            ->method('isSetFlag')
            ->willReturnCallback(static fn (string $path): bool => in_array($path, [
                'ergonode_attributes/cron/status',
                'ergonode_attributes/mapping/map_identical_codes',
                'ergonode_attributes/options/synchronize_sort_order',
                'ergonode_attributes/options/delete_missing_magento_options',
            ], true));

        $provider = new ProductAttributeConfigProvider($scopeConfig);

        self::assertTrue($provider->isImportCronEnabled());
        self::assertTrue($provider->shouldMapIdenticalCodes());
        self::assertTrue($provider->shouldSynchronizeOptionSortOrder());
        self::assertTrue($provider->shouldDeleteMissingMagentoOptions());
    }
}
