<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Attribute;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeAutoMatcher;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AutoMatch extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::attribute_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly AttributeAutoMatcher $attributeAutoMapper
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $payload = $this->payloadDecoder->decode((string)$this->getRequest()->getParam('payload', ''));
            $mappings = $this->payloadDecoder->requireList(
                $payload,
                'mappings',
                'Invalid attribute mapping snapshot.'
            );
            $visibility = isset($payload['visibility']) && is_array($payload['visibility'])
                ? $payload['visibility']
                : [];

            return $result->setData(
                [
                'success' => true,
                ] + $this->attributeAutoMapper->suggest($mappings, $visibility)
            );
        } catch (LocalizedException $exception) {
            return $result->setData(
                [
                'success' => false,
                'message' => $exception->getMessage(),
                ]
            );
        } catch (Throwable) {
            return $result->setData(
                [
                'success' => false,
                'message' => (string)__('Unable to prepare automatic attribute mappings.'),
                ]
            );
        }
    }
}
