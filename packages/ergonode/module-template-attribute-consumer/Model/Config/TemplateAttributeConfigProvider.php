<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class TemplateAttributeConfigProvider
{
    private const string XML_PATH_SYNC_ATTRIBUTES = 'ergonode_templates/import/sync_attributes';
    private const string XML_PATH_SYNC_SECTIONS = 'ergonode_templates/import/sync_sections';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function shouldSyncAttributes(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_ATTRIBUTES);
    }

    public function shouldSyncSections(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_SECTIONS);
    }
}
