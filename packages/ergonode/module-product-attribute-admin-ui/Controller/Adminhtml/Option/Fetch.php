<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Option;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\RemoteAttributeMetadataSource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Fetch extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_mapping';

    public function __construct(
        Context $context,
        private readonly MappingReaderInterface $mappingReader,
        private readonly RemoteAttributeMetadataSource $source
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $mappingId = (int)$this->getRequest()->getParam('attribute_mapping_id');
        $row = $mappingId > 0 ? $this->mappingReader->getAttributeRow($mappingId) : null;
        $attributeCode = trim((string)($row['ergonode_attribute_code'] ?? ''));
        if ($attributeCode === '') {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Options require a saved attribute mapping.'),
            ]);
        }

        try {
            return $result->setData(['success' => true] + $this->source->refreshOptions($attributeCode));
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to fetch Ergonode options.'),
            ]);
        }
    }
}
