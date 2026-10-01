<?php

declare(strict_types=1);

namespace Ergonode\Language\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Ergonode\Language\Api\StoreViewProviderInterface;

class StoreViewProvider implements StoreViewProviderInterface
{
    private const string XML_PATH_LOCALE = 'general/locale/code';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getStoreViews(): array
    {
        $views = [[
            'id' => Store::DEFAULT_STORE_ID,
            'code' => 'admin',
            'name' => (string)__('Admin / Default Values'),
            'locale' => trim((string)$this->scopeConfig->getValue(self::XML_PATH_LOCALE)),
            'website' => (string)__('Global'),
            'group' => (string)__('All Store Views'),
        ]];

        foreach ($this->storeManager->getStores(false) as $store) {
            $storeId = (int)$store->getId();
            $group = $this->storeManager->getGroup((int)$store->getStoreGroupId());
            $website = $this->storeManager->getWebsite((int)$store->getWebsiteId());
            $views[] = [
                'id' => $storeId,
                'code' => (string)$store->getCode(),
                'name' => (string)$store->getName(),
                'locale' => trim((string)$this->scopeConfig->getValue(
                    self::XML_PATH_LOCALE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                )),
                'website' => (string)$website->getName(),
                'group' => (string)$group->getName(),
            ];
        }

        usort(
            $views,
            static fn (array $left, array $right): int => [
                $left['id'] === Store::DEFAULT_STORE_ID ? 0 : 1,
                strtolower($left['website']),
                strtolower($left['group']),
                strtolower($left['name']),
            ] <=> [
                $right['id'] === Store::DEFAULT_STORE_ID ? 0 : 1,
                strtolower($right['website']),
                strtolower($right['group']),
                strtolower($right['name']),
            ]
        );

        return $views;
    }

    public function getStoreViewMap(): array
    {
        $result = [];

        foreach ($this->getStoreViews() as $storeView) {
            $result[$storeView['id']] = $storeView;
        }

        return $result;
    }
}
