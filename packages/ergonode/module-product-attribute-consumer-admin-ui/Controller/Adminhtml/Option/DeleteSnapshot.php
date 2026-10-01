<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Controller\Adminhtml\Option;

use Ergonode\AttributeConsumer\Api\OptionSnapshotRemoverInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class DeleteSnapshot extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_save';

    public function __construct(
        Context $context,
        private readonly AttributeMappingProvider $mappingProvider,
        private readonly OptionSnapshotRemoverInterface $snapshotRemover
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $this->snapshotRemover->remove(
                $this->getAttributeCode(),
                (string)$this->getRequest()->getParam('code', '')
            );

            return $result->setData(
                [
                'success' => true,
                'message' => (string)__('The item has been removed from this list.'),
                ]
            );
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return $result->setData(
                [
                'success' => false,
                'message' => (string)__('Unable to remove the item from this list.'),
                ]
            );
        }
    }

    private function getAttributeCode(): string
    {
        $mapping = $this->mappingProvider->getMappingRow(
            (int)$this->getRequest()->getParam('attribute_mapping_id')
        );
        $attributeCode = trim((string)($mapping['ergonode_attribute_code'] ?? ''));
        if (!$mapping || $attributeCode === '') {
            throw new LocalizedException(__('Options require a saved attribute mapping.'));
        }

        return $attributeCode;
    }
}
