<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping;

use Ergonode\Category\Api\CategoryLayoutValidatorInterface;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Validate extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly CategoryLayoutValidatorInterface $validator
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $payload = $this->payloadDecoder->decode((string)$this->getRequest()->getParam('payload', ''));
            $this->validator->validate(
                (int)($payload['category_tree_id'] ?? 0),
                is_array($payload['categories'] ?? null) ? $payload['categories'] : []
            );
            return $result->setData(['success' => true]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        }
    }
}
