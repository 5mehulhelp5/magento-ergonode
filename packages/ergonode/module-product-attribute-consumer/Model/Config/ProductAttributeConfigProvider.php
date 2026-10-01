<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ProductAttributeConfigProvider
{
    public const string XML_PATH_IMPORT_CRON_ENABLED = 'ergonode_attributes/cron/status';
    public const string XML_PATH_MAP_IDENTICAL_CODES = 'ergonode_attributes/mapping/map_identical_codes';
    public const string XML_PATH_SYNCHRONIZE_OPTION_SORT_ORDER =
        'ergonode_attributes/options/synchronize_sort_order';
    public const string XML_PATH_DELETE_MISSING_MAGENTO_OPTIONS =
        'ergonode_attributes/options/delete_missing_magento_options';

    public const string XML_PATH_IMPORT_CRON_SCHEDULE = 'ergonode_attributes/cron/schedule';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isImportCronEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_IMPORT_CRON_ENABLED);
    }

    public function shouldMapIdenticalCodes(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_MAP_IDENTICAL_CODES);
    }

    public function shouldSynchronizeOptionSortOrder(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SYNCHRONIZE_OPTION_SORT_ORDER);
    }

    public function shouldDeleteMissingMagentoOptions(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DELETE_MISSING_MAGENTO_OPTIONS);
    }
}
