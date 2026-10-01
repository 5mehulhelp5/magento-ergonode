<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisherAdminUi\Block\Adminhtml;

use Ergonode\TemplatePublisherAdminUi\Controller\Adminhtml\Template\Create;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class TemplatePublisher extends Template
{
    public function __construct(
        Context $context,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'form_key' => $this->getFormKey(),
            'urls' => [
                'create' => $this->getUrl('ergonode_template_publication/template/create'),
            ],
        ]);
    }

    public function canCreateTemplate(): bool
    {
        return $this->getAuthorization()->isAllowed(Create::ADMIN_RESOURCE);
    }
}
