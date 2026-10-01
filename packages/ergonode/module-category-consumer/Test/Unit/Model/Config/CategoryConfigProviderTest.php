<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Config;

use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\Core\Model\Config\ConfigProvider as ErgonodeConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class CategoryConfigProviderTest extends TestCase
{
    public function testReadsNativeSystemConfigurationPaths(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('ergonode_categories/cron/status')
            ->willReturn(true);
        $ergonodeConfig = $this->createStub(ErgonodeConfigProvider::class);
        $ergonodeConfig->method('isEnabled')->willReturn(true);

        $provider = new CategoryConfigProvider($scopeConfig, $ergonodeConfig);

        self::assertTrue($provider->isEnabled());
        self::assertTrue($provider->isCronEnabled());
    }
}
