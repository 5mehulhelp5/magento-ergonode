<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeAdminUi\Block\Adminhtml;

use Ergonode\TemplateAdminUi\Api\WorkspaceConfigProviderInterface;
use Magento\Backend\Block\Template;

class Structure extends Template implements WorkspaceConfigProviderInterface
{
    public function getConfig(): array
    {
        return [
            'urls' => ['structure' => $this->getUrl('ergonode/template/structure')],
            'structureIcons' => [
                'ergonode' => $this->getViewFileUrl('Ergonode_CoreAdminUi::images/m2_configuration.svg'),
                'magento' => $this->getViewFileUrl('Ergonode_CoreAdminUi::images/magento-mark.svg'),
            ],
        ];
    }
}
