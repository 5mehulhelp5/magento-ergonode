<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeOptionSyncer;
use Ergonode\Category\Model\Config\Source\CategoryTreeOptions;

class RefreshTreeOptions extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_manage';

    public function __construct(
        Action\Context $context,
        private readonly CategoryTreeOptionSyncer $treeOptionsSyncer,
        private readonly CategoryTreeOptions $categoryTreeOptions,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $stats = $this->treeOptionsSyncer->sync();

            return $result->setData([
                'success' => true,
                'message' => (string)__('Loaded %1 Ergonode category trees.', $stats['synced']),
                'options' => $this->categoryTreeOptions->toOptionArray(),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Exception $exception) {
            $this->logger->error('Could not refresh Ergonode category tree options.', [
                'exception' => $exception,
            ]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('Could not load category trees from Ergonode.'),
            ]);
        }
    }
}
