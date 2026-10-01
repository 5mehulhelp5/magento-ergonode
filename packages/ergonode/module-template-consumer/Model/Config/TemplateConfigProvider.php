<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class TemplateConfigProvider
{
    private const string XML_PATH_CREATE_ATTRIBUTE_SETS = 'ergonode_templates/import/create_attribute_sets';
    private const string XML_PATH_CRON_ENABLED = 'ergonode_templates/cron/status';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function shouldCreateAttributeSets(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CREATE_ATTRIBUTE_SETS);
    }

    public function isCronEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CRON_ENABLED);
    }
}
