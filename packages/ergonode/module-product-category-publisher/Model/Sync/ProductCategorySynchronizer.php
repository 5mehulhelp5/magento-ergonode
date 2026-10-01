<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Sync;

use Ergonode\ProductCategoryPublisher\Api\Data\ProductCategoryStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductBatchSynchronizerInterface;
use Ergonode\ProductPublisher\Api\ProductSynchronizerInterface;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\ProductPublisher\Model\Sync\ProductSynchronizer;
use Magento\Framework\Exception\LocalizedException;

class ProductCategorySynchronizer implements ProductSynchronizerInterface, ProductBatchSynchronizerInterface
{
    public function __construct(
        private readonly ProductSynchronizer $productSynchronizer,
        private readonly ProductCategoryPublicationSynchronizer $categorySynchronizer,
        private readonly ProductCategoryPublicationPolicy $categoryPolicy
    ) {
    }

    public function synchronize(
        ProductStateInterface $desiredState,
        string $mode = self::MODE_UPDATE
    ): ProductSynchronizationResultInterface {
        return $this->synchronizeBatch([$desiredState], $mode)[0];
    }

    public function synchronizeBatch(array $desiredStates, string $mode = self::MODE_UPDATE): array
    {
        $this->validateInput($desiredStates, $mode);
        $eligible = [];
        $resultsBySku = [];
        foreach ($desiredStates as $state) {
            $message = $state instanceof ProductCategoryStateInterface
                ? $this->categoryPolicy->getBlockingMessage($state)
                : null;
            if ($message === null) {
                $eligible[] = $state;
                continue;
            }
            $resultsBySku[$state->getSku()] = new ProductSynchronizationResult(
                $state->getSku(),
                ProductSynchronizationResultInterface::STATUS_FAILED,
                message: $message
            );
        }
        if ($eligible !== []) {
            foreach ($this->categorySynchronizer->synchronize(
                $eligible,
                $this->productSynchronizer->synchronizeBatch($eligible, $mode)
            ) as $result) {
                $resultsBySku[$result->getSku()] = $result;
            }
        }

        return array_map(
            static fn (ProductStateInterface $state): ProductSynchronizationResultInterface =>
                $resultsBySku[$state->getSku()],
            $desiredStates
        );
    }

    /** @param ProductStateInterface[] $desiredStates */
    private function validateInput(array $desiredStates, string $mode): void
    {
        if (!in_array($mode, [self::MODE_CREATE_ONLY, self::MODE_UPDATE, self::MODE_RECONCILE], true)) {
            throw new LocalizedException(__('Unsupported product synchronization mode "%1".', $mode));
        }
        $skus = [];
        foreach ($desiredStates as $state) {
            if (!$state instanceof ProductStateInterface) {
                throw new LocalizedException(
                    __('Every product synchronization item must implement ProductStateInterface.')
                );
            }
            if (isset($skus[$state->getSku()])) {
                throw new LocalizedException(__('Duplicate product synchronization SKU "%1".', $state->getSku()));
            }
            $skus[$state->getSku()] = true;
        }
    }
}
