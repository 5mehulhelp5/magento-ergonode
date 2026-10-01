<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Publication extends Template
{
    public function __construct(Context $context, private readonly Json $json, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getInitialization(): string
    {
        return $this->json->serialize([
            'Ergonode_ProductAdminUi/js/publication-grid' => [
                'dataUrl' => $this->getUrl('ergonode_product/product/data'),
                'snapshotUrl' => $this->getUrl('ergonode_product/product/snapshot'),
            ],
        ]);
    }
}
