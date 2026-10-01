<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Controller\Adminhtml\Category\Attribute;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\AttributeMappingSaver;
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
        private readonly AttributeMappingSaver $mappingUpdater
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $payload = $this->payloadDecoder->decode(
                (string)$this->getRequest()->getParam('payload', '')
            );
            $stats = $this->mappingUpdater->save(
                $this->payloadDecoder->requireList(
                    $payload,
                    'mappings',
                    'Invalid category attribute mapping snapshot.'
                ),
                is_array($payload['visibility'] ?? null) ? $payload['visibility'] : []
            );

            return $result->setData([
                'success' => true,
                'message' => (string)__('Category attribute mappings have been saved.'),
                'stats' => $stats,
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to save category attribute mappings.'),
            ]);
        }
    }
}
