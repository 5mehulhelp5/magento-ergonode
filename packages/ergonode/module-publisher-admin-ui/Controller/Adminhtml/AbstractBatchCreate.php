<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Controller\Adminhtml;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;

abstract class AbstractBatchCreate extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly Json $json
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $items = $this->publishBatch($this->decodeItems());

            return $result->setData([
                'success' => true,
                'items' => $items,
                'stats' => $this->stats($items),
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
                'message' => (string)__('Unable to process the Ergonode %1 batch.', $this->getBatchEntityName()),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    abstract protected function publishBatch(array $items): array;

    abstract protected function getBatchEntityName(): string;

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    protected function stats(array $items): array
    {
        $failed = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['status'] ?? '') === 'failed'
        ));

        return [
            'processed' => count($items),
            'successful' => count($items) - $failed,
            'failed' => $failed,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function decodeItems(): array
    {
        $payload = trim((string)$this->getRequest()->getParam('items', ''));
        if ($payload === '') {
            throw new LocalizedException(__('Missing %1 batch payload.', $this->getBatchEntityName()));
        }
        $items = $this->json->unserialize($payload);
        if (!is_array($items)) {
            throw new LocalizedException(__('Invalid %1 batch payload.', $this->getBatchEntityName()));
        }

        return array_values(array_filter($items, 'is_array'));
    }
}
