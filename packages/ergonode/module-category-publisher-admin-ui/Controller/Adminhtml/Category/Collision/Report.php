<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Category\Collision;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationCollisionLogger;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Report extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_save';

    public function __construct(
        Context $context,
        private readonly CategoryCreationCollisionLogger $collisionLogger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $categoryTreeId = (int)$this->getRequest()->getParam('category_tree_id', 0);
        $code = trim((string)$this->getRequest()->getParam('code', ''));
        $skippedCategoryId = (int)$this->getRequest()->getParam('skipped_magento_category_id', 0);
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($categoryTreeId <= 0 || $code === '' || $skippedCategoryId <= 0) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Invalid category collision report.'),
            ]);
        }

        $winningCategoryId = (int)$this->getRequest()->getParam('winning_magento_category_id', 0);
        $this->collisionLogger->warning(
            $categoryTreeId,
            $code,
            $skippedCategoryId,
            trim((string)$this->getRequest()->getParam('skipped_label', '')),
            'local_tree',
            $winningCategoryId > 0 ? $winningCategoryId : null,
            trim((string)$this->getRequest()->getParam('winning_label', '')) ?: null
        );

        return $result->setData(['success' => true]);
    }
}
