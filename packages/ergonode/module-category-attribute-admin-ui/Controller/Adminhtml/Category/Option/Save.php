<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Controller\Adminhtml\Category\Option;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly OptionMappingSaver $mappingUpdater
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $stats = $this->saveMappings();

            return $result->setData([
                'success' => true,
                'message' => (string)__('Category option mappings have been saved.'),
                'stats' => $stats,
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to save category option mappings.'),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function saveMappings(): array
    {
        $payload = $this->payloadDecoder->decode(
            (string)$this->getRequest()->getParam('payload', '')
        );
        $mappingId = (int)($payload['attribute_mapping_id'] ?? 0);
        if ($mappingId <= 0) {
            throw new LocalizedException(__('Missing category attribute mapping context.'));
        }
        return $this->mappingUpdater->save(
            $mappingId,
            $this->payloadDecoder->requireList(
                $payload,
                'mappings',
                'Invalid category attribute mapping snapshot.'
            ),
            is_array($payload['visibility'] ?? null) ? $payload['visibility'] : []
        );
    }
}
