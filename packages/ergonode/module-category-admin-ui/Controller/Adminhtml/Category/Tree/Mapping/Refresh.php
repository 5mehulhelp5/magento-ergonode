<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping;

use Ergonode\Category\Api\CategoryTreeRefreshServiceInterface;
use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_refresh';

    public function __construct(
        Context $context,
        private readonly CategoryTreeRefreshServiceInterface $refreshService,
        private readonly CategoryTreeMappingUiProvider $mappingUiProvider,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $categoryTreeId = (int)$this->getRequest()->getParam('category_tree_id', 0);

        try {
            $this->refreshService->refresh($categoryTreeId);
            $config = $this->mappingUiProvider->getConfig();

            return $result->setData([
                'success' => true,
                'message' => (string)__('Category data has been refreshed.'),
                'categories' => $config['categories'],
                'magento_categories' => $config['magento_categories'],
                'source_issues' => $config['source_issues'],
                'category_trees' => $config['category_trees'],
            ]);
        } catch (GraphQlRequestException $exception) {
            return $result->setData(
                [
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'failure_type' => $exception->getFailureType(),
                    'retry_after_seconds' => $exception->getRetryAfterSeconds(),
                ] + $this->sourceFeedback()
            );
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ] + $this->sourceFeedback());
        } catch (Throwable $exception) {
            $this->logger->error('Unable to refresh category mapping data.', [
                'category_tree_id' => $categoryTreeId,
                'exception' => $exception,
            ]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to refresh Ergonode categories.'),
            ] + $this->sourceFeedback());
        }
    }
    /** @return array<string, mixed> */
    private function sourceFeedback(): array
    {
        $config = $this->mappingUiProvider->getConfig();

        return [
            'source_issues' => $config['source_issues'],
            'category_trees' => $config['category_trees'],
        ];
    }
}
