<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping;

use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Load extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping';

    public function __construct(
        Context $context,
        private readonly CategoryTreeMappingUiProvider $categoryTreeMappingUiProvider
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $config = $this->categoryTreeMappingUiProvider->getConfig();

        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData([
                'success' => $config['current_category_tree'] !== null,
                'message' => $config['status']['error'],
                'config' => $config,
            ]);
    }
}
