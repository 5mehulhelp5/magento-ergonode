<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping;

use Ergonode\Category\Api\CategoryLayoutSaverInterface;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly CategoryLayoutSaverInterface $categoryLayoutSaver
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
            $categoryTreeId = (int)(
                $payload['category_tree_id'] ?? $this->getRequest()->getParam('category_tree_id', 0)
            );
            if ($categoryTreeId <= 0) {
                throw new LocalizedException(__('Category Tree is required.'));
            }
            $items = isset($payload['categories']) && is_array($payload['categories']) ? $payload['categories'] : [];
            $visibility = isset($payload['visibility']) && is_array($payload['visibility'])
                ? $payload['visibility']
                : [];
            $stats = $this->categoryLayoutSaver->save($categoryTreeId, $items, $visibility);

            return $result->setData([
                'success' => true,
                'message' => (string)__('Category layout has been saved.'),
                'category_tree_id' => $categoryTreeId,
                'stats' => $stats,
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
        } catch (AuthenticationException $exception) {
            return $result->setData([
                'success' => false,
                'failure_type' => 'authentication_required',
                'message' => $exception->getMessage(),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to save category layout.'),
            ]);
        }
    }
}
