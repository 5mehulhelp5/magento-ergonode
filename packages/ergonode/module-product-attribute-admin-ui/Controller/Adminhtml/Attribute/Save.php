<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Attribute;

use Ergonode\ProductAttribute\Api\MappingSaveNoticeCollectorInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::attribute_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly AttributeMappingSaver $attributeMappingSaver,
        private readonly MappingSaveNoticeCollectorInterface $noticeCollector,
        private readonly AttributeMappingProvider $mappingProvider
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
            $stats = $this->attributeMappingSaver->save(
                $mappings,
                isset($payload['visibility']) && is_array($payload['visibility']) ? $payload['visibility'] : []
            );
            $warnings = $this->noticeCollector->consumeWarnings();
            $this->mappingProvider->clearCache();

            return $result->setData(
                [
                'success' => true,
                'tone' => $warnings === [] ? 'success' : 'warning',
                'message' => $warnings === []
                    ? (string)__('Attribute mappings have been saved.')
                    : implode(' ', $warnings),
                'stats' => $stats,
                'validation' => $this->mappingProvider->getValidationMessages(),
                ]
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
                'message' => (string)__('Unable to save attribute mappings.'),
                ]
            );
        }
    }
}
