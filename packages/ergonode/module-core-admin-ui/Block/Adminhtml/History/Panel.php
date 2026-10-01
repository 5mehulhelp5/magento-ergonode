<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml\History;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\JsonHexTag;

class Panel extends Template
{
    public function __construct(
        Context $context,
        private readonly JsonHexTag $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'urls' => [
                'operations' => $this->getUrl((string)$this->getData('operations_route')),
                'history' => $this->getUrl((string)$this->getData('history_route')),
            ],
        ]);
    }
}
