<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Category;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryFormPublisher;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Create extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_save';

    public function __construct(
        Context $context,
        private readonly CategoryFormPublisher $categoryFormPublisher
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $code = $this->categoryFormPublisher->publish(
                (int)$this->getRequest()->getParam('category_id', 0)
            );

            return $result->setData([
                'success' => true,
                'code' => $code,
                'message' => (string)__('The category has been created in Ergonode and mapped with Magento.'),
            ]);
        } catch (RetryAfterExceptionInterface $exception) {
            if ($exception instanceof GraphQlRequestException && !$exception->isSafeToRetry()) {
                return $result->setData([
                    'success' => false,
                    'failure_type' => $exception->getFailureType(),
                    'message' => $exception->getMessage(),
                ]);
            }
            return $result->setData([
                'success' => false,
                'failure_type' => 'retryable',
                'retry_after_seconds' => max(1, $exception->getRetryAfterSeconds() ?? 5),
                'message' => $exception->getMessage(),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to create and map the category in Ergonode.'),
            ]);
        }
    }
}
