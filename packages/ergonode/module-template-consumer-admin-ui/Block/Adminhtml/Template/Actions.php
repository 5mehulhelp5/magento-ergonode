<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumerAdminUi\Block\Adminhtml\Template;

use Ergonode\TemplateAdminUi\Api\WorkspaceConfigProviderInterface;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Actions extends Template implements WorkspaceConfigProviderInterface
{
    private const string ADMIN_RESOURCE_REFRESH = 'Ergonode_TemplateConsumer::template_refresh';
    private const string ADMIN_RESOURCE_SAVE = 'Ergonode_TemplateConsumer::template_save';
    private const string ADMIN_RESOURCE_SYNC = 'Ergonode_TemplateConsumer::template_sync';

    public function __construct(
        Context $context,
        private readonly TemplateConfigProvider $configProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfig(): array
    {
        return [
            'can_create_attribute_sets' => $this->configProvider->shouldCreateAttributeSets(),
            'can_refresh_templates' => $this->canRefreshTemplates(),
            'can_delete_snapshots' => $this->canDeleteSnapshots(),
            'urls' => [
                'refresh' => $this->getUrl('ergonode/template/refresh'),
                'sync' => $this->getUrl('ergonode/template/sync'),
                'delete_snapshot' => $this->getUrl('ergonode/template/deleteSnapshot'),
            ],
        ];
    }

    public function canRefreshTemplates(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ADMIN_RESOURCE_REFRESH);
    }

    public function canDeleteSnapshots(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ADMIN_RESOURCE_SAVE);
    }

    public function canSynchronize(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ADMIN_RESOURCE_SYNC);
    }
}
