<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model;

use Ergonode\Language\Model\StoreViewProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class StoreViewProviderTest extends TestCase
{
    public function testAlwaysExposesAdminDefaultValuesScopeAsStoreIdZero(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->with(false)->willReturn([]);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->with('general/locale/code')->willReturn('pl_PL');

        self::assertSame([[
            'id' => 0,
            'code' => 'admin',
            'name' => 'Admin / Default Values',
            'locale' => 'pl_PL',
            'website' => 'Global',
            'group' => 'All Store Views',
        ]], (new StoreViewProvider($storeManager, $scopeConfig))->getStoreViews());
    }
}
