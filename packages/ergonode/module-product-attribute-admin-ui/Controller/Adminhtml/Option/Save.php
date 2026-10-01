<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Option;

use Ergonode\ProductAttribute\Api\MappingSaveNoticeCollectorInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly OptionMappingSaver $optionMappingSaver,
        private readonly MappingSaveNoticeCollectorInterface $noticeCollector
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $stats = $this->saveMappings();
            $warnings = $this->noticeCollector->consumeWarnings();

            return $result->setData(
                [
                'success' => true,
                'tone' => $warnings === [] ? 'success' : 'warning',
                'message' => $warnings === []
                    ? (string)__('Option mappings have been saved.')
                    : implode(' ', $warnings),
                'stats' => $stats,
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
                'message' => (string)__('Unable to save option mappings.'),
                ]
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function saveMappings(): array
    {
        $payload = $this->payloadDecoder->decode((string)$this->getRequest()->getParam('payload', ''));
        $mappingId = (int)($payload['attribute_mapping_id'] ?? 0);
        if ($mappingId <= 0) {
            throw new LocalizedException(__('Missing attribute mapping context.'));
        }
        $mappings = $this->payloadDecoder->requireList(
            $payload,
            'mappings',
            'Invalid option mapping snapshot.'
        );

        return $this->optionMappingSaver->save(
            $mappingId,
            $mappings,
            isset($payload['visibility']) && is_array($payload['visibility']) ? $payload['visibility'] : []
        );
    }
}
