<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Model\Batch;

use Ergonode\CategoryAttributePublisherAdminUi\Model\ErgonodeCategoryAttributeCreator;
use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class CategoryAttributeBatchPublisher
{
    public function __construct(
        private readonly ErgonodeCategoryAttributeCreator $attributeCreator,
        private readonly int $maxBatchSize = 50
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array{
     *     code: string,
     *     label: string,
     *     status: string,
     *     message: string,
     *     mapping?: array<string, mixed>
     * }>
     */
    public function publish(array $items): array
    {
        $this->validateBatch($items);
        $results = [];

        foreach ($items as $item) {
            $source = $this->normalizeSource($item);
            try {
                $this->validateSource($source);
                $state = $this->attributeCreator->synchronizeFromMagento(
                    (string)$source['code'],
                    (string)$source['target_type']
                );
                $results[] = $this->success($source, strtolower($state->getScope()));
            } catch (Throwable $exception) {
                if ($exception instanceof RetryAfterExceptionInterface) {
                    throw $exception;
                }
                $results[] = $this->failure($source, $exception);
            }
        }

        return $results;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function validateBatch(array $items): void
    {
        if ($items === []) {
            throw new LocalizedException(__('Category attribute batch cannot be empty.'));
        }
        if (count($items) > max(1, $this->maxBatchSize)) {
            throw new LocalizedException(__(
                'Category attribute batch contains %1 items; the maximum is %2.',
                count($items),
                max(1, $this->maxBatchSize)
            ));
        }
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function normalizeSource(array $item): array
    {
        return [
            'code' => trim((string)($item['code'] ?? '')),
            'label' => trim((string)($item['label'] ?? $item['code'] ?? '')),
            'target_type' => trim((string)($item['target_type'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $source */
    private function validateSource(array $source): void
    {
        if ($source['code'] === '' || $source['label'] === '' || $source['target_type'] === '') {
            throw new LocalizedException(__(
                'Magento category attribute code, label and Ergonode type are required.'
            ));
        }
    }

    /**
     * @param array<string, mixed> $source
     * @return array{code: string, label: string, status: string, message: string, mapping: array<string, mixed>}
     */
    private function success(array $source, string $scope): array
    {
        return [
            'code' => (string)$source['code'],
            'label' => (string)$source['label'],
            'status' => 'synchronized',
            'message' => (string)__('Category attribute has been synchronized with Ergonode.'),
            'mapping' => [
                'code' => (string)$source['code'],
                'label' => (string)$source['label'],
                'type' => (string)$source['target_type'],
                'scope' => $scope,
                'pending_create' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array{code: string, label: string, status: string, message: string}
     */
    private function failure(array $source, Throwable $exception): array
    {
        return [
            'code' => (string)$source['code'],
            'label' => (string)$source['label'],
            'status' => 'failed',
            'message' => $exception instanceof LocalizedException
                ? $exception->getMessage()
                : (string)__('Unable to create the Ergonode category attribute.'),
        ];
    }
}
