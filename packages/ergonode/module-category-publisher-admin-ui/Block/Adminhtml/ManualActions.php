<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Block\Adminhtml;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Ergonode\PublisherAdminUi\Api\WriteReadinessProviderInterface;
use Magento\Backend\Block\Template;
use Magento\Framework\Serialize\Serializer\Json;

class ManualActions extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Json $json,
        private readonly WriteReadinessProviderInterface $writeReadiness,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'form_key' => $this->getFormKey(),
            'write_readiness' => $this->writeReadiness->getStatus(),
            'category_batch_size' => CategoryBatchPublisher::MAX_BATCH_SIZE,
            'logo_url' => $this->getViewFileUrl(
                'Ergonode_CoreAdminUi::images/m2_configuration.svg'
            ),
            'urls' => [
                'login' => $this->getUrl('ergonode/rest/login'),
                'status' => $this->getUrl('ergonode_manual/session/status'),
                'create_tree' => $this->getUrl('ergonode_manual/tree/create'),
                'create_category_batch' => $this->getUrl('ergonode_manual/category_batch/create'),
                'report_category_collision' => $this->getUrl('ergonode_manual/category_collision/report'),
            ],
        ]);
    }
}
