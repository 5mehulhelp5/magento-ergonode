<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping;

use Ergonode\Category\Api\CategorySnapshotRemoverInterface;
use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class DeleteSnapshot extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_save';

    public function __construct(
        Context $context,
        private readonly CategorySnapshotRemoverInterface $snapshotRemover,
        private readonly CategoryTreeMappingUiProvider $mappingUiProvider,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $categoryTreeId = (int)$this->getRequest()->getParam('category_tree_id', 0);
        $ergonodeCode = (string)$this->getRequest()->getParam('ergonode_code', '');

        try {
            $this->snapshotRemover->remove($categoryTreeId, $ergonodeCode);
            $config = $this->mappingUiProvider->getConfig();

            return $result->setData([
                'success' => true,
                'message' => (string)__('Category has been removed from this list.'),
                'categories' => $config['categories'],
                'magento_categories' => $config['magento_categories'],
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to remove an Ergonode category from the local tree snapshot.', [
                'category_tree_id' => $categoryTreeId,
                'ergonode_code' => $ergonodeCode,
                'exception' => $exception,
            ]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to remove the category from this list.'),
            ]);
        }
    }
}
