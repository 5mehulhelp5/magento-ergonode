<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Controller\Adminhtml\Attribute\Batch;

use Ergonode\ProductAttributePublisherAdminUi\Model\Batch\AttributeBatchPublisher;
use Ergonode\PublisherAdminUi\Controller\Adminhtml\AbstractBatchCreate;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Create extends AbstractBatchCreate
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::attribute_save';

    public function __construct(
        Context $context,
        Json $json,
        private readonly AttributeBatchPublisher $batchPublisher
    ) {
        parent::__construct($context, $json);
    }

    protected function publishBatch(array $items): array
    {
        return $this->batchPublisher->publish($items);
    }

    protected function getBatchEntityName(): string
    {
        return 'attribute';
    }
}
