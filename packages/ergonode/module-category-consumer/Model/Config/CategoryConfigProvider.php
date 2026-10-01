<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Ergonode\Core\Model\Config\ConfigProvider as ErgonodeConfigProvider;

class CategoryConfigProvider
{
    public const string XML_PATH_CRON_ENABLED = 'ergonode_categories/cron/status';
    public const string XML_PATH_CRON_SCHEDULE = 'ergonode_categories/cron/schedule';
    // Keep the persisted paths while their process and Admin presentation move to this module.
    public const string XML_PATH_DATA_ENABLED = 'ergonode_category_attributes/synchronization/status';
    public const string XML_PATH_DATA_CRON_ENABLED = 'ergonode_category_attributes/cron/status';
    public const string XML_PATH_DATA_CRON_SCHEDULE = 'ergonode_category_attributes/cron/schedule';
    public const string XML_PATH_NAME_MODE = 'ergonode_categories/synchronization/name_mode';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ErgonodeConfigProvider $ergonodeConfigProvider
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->ergonodeConfigProvider->isEnabled();
    }

    public function isCronEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CRON_ENABLED);
    }

    public function isDataSynchronizationEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DATA_ENABLED);
    }

    public function isDataCronEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DATA_CRON_ENABLED);
    }

    public function getNameMode(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_NAME_MODE);
    }
}
