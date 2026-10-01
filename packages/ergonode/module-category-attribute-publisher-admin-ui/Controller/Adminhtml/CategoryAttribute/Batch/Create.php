<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Controller\Adminhtml\CategoryAttribute\Batch;

use Ergonode\CategoryAttributePublisherAdminUi\Model\Batch\CategoryAttributeBatchPublisher;
use Ergonode\PublisherAdminUi\Controller\Adminhtml\AbstractBatchCreate;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Create extends AbstractBatchCreate
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_save';

    public function __construct(
        Context $context,
        Json $json,
        private readonly CategoryAttributeBatchPublisher $batchPublisher
    ) {
        parent::__construct($context, $json);
    }

    protected function publishBatch(array $items): array
    {
        return $this->batchPublisher->publish($items);
    }

    protected function getBatchEntityName(): string
    {
        return 'category attribute';
    }
}
