<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumerAdminUi\Block\Adminhtml;

use Ergonode\TemplateAdminUi\Api\WorkspaceConfigProviderInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\AuthorizationInterface;

class Placement extends Template implements WorkspaceConfigProviderInterface
{
    private const string ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_save';

    public function __construct(
        Context $context,
        private readonly AuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfig(): array
    {
        return ['urls' => ['manual_placement' => $this->getUrl('ergonode/template/manualPlacement')],
            'can_edit_placement' => $this->authorization->isAllowed(self::ADMIN_RESOURCE)];
    }
}
