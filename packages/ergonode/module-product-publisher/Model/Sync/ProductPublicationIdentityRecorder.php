<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationProductResolverInterface;

class ProductPublicationIdentityRecorder
{
    public function __construct(
        private readonly ProductPublicationProductResolverInterface $productResolver,
        private readonly ProductIdentityRegistryInterface $identityRegistry
    ) {
    }

    /**
     * @param ProductSynchronizationResultInterface[] $results
     * @param array<string, ProductStateInterface> $states
     */
    public function record(array $results, array $states = []): void
    {
        $skus = [];
        foreach ($results as $result) {
            if (!in_array($result->getStatus(), [
                ProductSynchronizationResultInterface::STATUS_SUCCESS,
                ProductSynchronizationResultInterface::STATUS_NOOP,
            ], true)) {
                continue;
            }
            $sku = trim($result->getSku());
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }
        if ($skus === []) {
            return;
        }
        ksort($skus);

        $productSkus = $this->productResolver->getProductSkusBySkus(array_values($skus));
        $existing = $this->identityRegistry->getIdentitiesByProductIds(array_keys($productSkus));
        $mapped = [];
        foreach ($productSkus as $productId => $magentoSku) {
            $state = $states[$magentoSku] ?? null;
            if (isset($existing[$productId]) || $state?->getIdentityMode() !== ProductIdentityInterface::MODE_MAPPED) {
                continue;
            }
            $mapped[(int)$productId] = (string)$state->getErgonodeSku();
        }
        if ($mapped !== []) {
            $this->identityRegistry->bindMappedBatch($mapped);
        }
        $this->identityRegistry->recordPublished($productSkus);
    }
}
