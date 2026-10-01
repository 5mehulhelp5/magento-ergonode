<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationBatchInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationProductResolverInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationResultWriterInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationSynchronizerInterface;
use Ergonode\ProductPublisher\Model\ResourceModel\ProductPublicationResultReader;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class ProductPublicationBatch implements ProductPublicationBatchInterface
{
    public function __construct(
        private readonly ProductPublicationProductResolverInterface $productResolver,
        private readonly ProductIdentityRegistryInterface $identityRegistry,
        private readonly ProductPublicationSynchronizerInterface $synchronizer,
        private readonly ProductPublicationResultWriterInterface $resultWriter,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        private readonly ProductPublicationResultReader $resultReader
    ) {
    }

    public function publish(array $productIds): array
    {
        if ($productIds === [] || count($productIds) > 50 || min($productIds) < 1) {
            throw new LocalizedException(__('Choose between 1 and 50 products for a publication batch.'));
        }
        $productIds = array_values(array_unique($productIds));
        $skus = $this->productResolver->getCurrentSkus($productIds);
        $held = $this->unconfirmedIdentityCreations(array_keys($skus));
        $pending = array_map(static fn (int $id): array => [
            'product_id' => $id,
            'status' => 'unconfirmed',
            'message' => (string)__('Publication started, but its final result has not been confirmed. '
                . 'Refresh the list to check the result before publishing again.'),
        ], array_keys(array_diff_key($skus, $held)));
        if ($pending !== []) {
            $this->resultWriter->save($pending);
        }
        try {
            $items = $this->publishResolved($productIds, $skus, $held);
        } catch (Throwable $exception) {
            foreach ($pending as &$item) {
                $item['message'] = $exception instanceof LocalizedException
                    ? $exception->getMessage()
                    : (string)__(
                        'Publication ended unexpectedly. Its result may be partial; check the application log.'
                    );
            }
            unset($item);
            if ($pending !== []) {
                $this->resultWriter->save($pending);
            }
            throw $exception;
        }
        $newItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => !isset($held[$item['product_id']])
        ));
        $recordedAt = $newItems !== [] ? $this->resultWriter->save($newItems) : gmdate('Y-m-d H:i:s');

        return array_map(static fn (array $item): array => [
            ...$item,
            'publication_at' => $held[$item['product_id']] ?? $recordedAt,
        ], $items);
    }

    /**
     * @param int[] $productIds
     * @return array<int, string> Product ID to UTC recorded time.
     */
    private function unconfirmedIdentityCreations(array $productIds): array
    {
        if ($this->identityModeProvider->getMode() !== ProductIdentityModeProviderInterface::MODE_ASSIGNED) {
            return [];
        }
        $unconfirmed = $this->resultReader->getUnconfirmedTimes($productIds);
        if ($unconfirmed === []) {
            return [];
        }
        $identities = $this->identityRegistry->getIdentitiesByProductIds(array_keys($unconfirmed));

        return array_diff_key($unconfirmed, $identities);
    }

    /**
     * @param int[] $productIds
     * @param array<int, string> $skus
     * @param array<int, string> $held
     * @return list<array{product_id: int, code: string, status: string, message: string}>
     */
    private function publishResolved(array $productIds, array $skus, array $held): array
    {
        $candidateSkus = array_diff_key($skus, $held);
        $failures = $this->identityRegistry->getStabilityFailures($candidateSkus);
        $publishable = array_diff_key($candidateSkus, $failures);
        $results = $publishable === []
            ? []
            : $this->synchronizer->synchronize(array_values($publishable));
        $items = [];
        foreach ($productIds as $productId) {
            $sku = $skus[$productId] ?? '';
            if (isset($held[$productId])) {
                $items[] = [
                    'product_id' => $productId,
                    'code' => $sku,
                    'status' => 'unconfirmed',
                    'message' => (string)__(
                        'An earlier assigned-SKU creation has an unconfirmed result without a saved identity. '
                        . 'No new product was created. '
                        . 'Verify Ergonode independently and reconcile the native SKU binding before retrying. '
                        . 'If no remote product exists, clear this stored unconfirmed result after verification.'
                    ),
                ];
                continue;
            }
            $result = $results[$sku] ?? null;
            $message = $failures[$productId] ?? ($sku === ''
                ? (string)__('The Magento product no longer exists.')
                : ($result?->getMessage() ?? ''));
            if ($result?->isSuccessful() && $result->getWarnings() !== []) {
                $message = implode("\n", $result->getWarnings());
            }
            $items[] = [
                'product_id' => $productId,
                'code' => $sku !== '' ? $sku : (string)$productId,
                'status' => isset($failures[$productId]) || $sku === '' ? 'warning' : $this->status($result),
                'message' => $message,
            ];
        }

        return $items;
    }

    private function status(?ProductSynchronizationResultInterface $result): string
    {
        if ($result === null) {
            return 'failed';
        }

        return match ($result->getStatus()) {
            ProductSynchronizationResultInterface::STATUS_SUCCESS,
            ProductSynchronizationResultInterface::STATUS_NOOP => $result->getWarnings() !== []
                ? 'warning' : 'success',
            ProductSynchronizationResultInterface::STATUS_ATTENTION => 'unconfirmed',
            ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE,
            ProductSynchronizationResultInterface::STATUS_LOCAL_WARNING => 'warning',
            default => 'failed',
        };
    }
}
