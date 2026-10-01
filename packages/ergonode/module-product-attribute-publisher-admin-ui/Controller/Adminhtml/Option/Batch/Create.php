<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Controller\Adminhtml\Option\Batch;

use Ergonode\ProductAttributePublisherAdminUi\Model\Batch\OptionBatchPublisher;
use Ergonode\PublisherAdminUi\Controller\Adminhtml\AbstractBatchCreate;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Create extends AbstractBatchCreate
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_save';

    public function __construct(
        Context $context,
        Json $json,
        private readonly OptionBatchPublisher $batchPublisher
    ) {
        parent::__construct($context, $json);
    }

    protected function publishBatch(array $items): array
    {
        return $this->batchPublisher->publish(
            (int)$this->getRequest()->getParam('attribute_mapping_id', 0),
            $items
        );
    }

    protected function getBatchEntityName(): string
    {
        return 'option';
    }
}
