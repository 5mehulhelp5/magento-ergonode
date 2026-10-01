<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductConsumer\Model\Data\ResolvedProductTarget;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Framework\Exception\LocalizedException;

class ProductIdentityTargetResolver
{
    public function __construct(
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly MappedMagentoSkuResolver $magentoSkuResolver,
        private readonly ProductTargetResolver $targetResolver,
        private readonly MagentoSkuSynchronizer $magentoSkuSynchronizer
    ) {
    }

    public function resolve(RemoteProduct $source): ResolvedProductTarget
    {
        $identity = $this->identityService->getIdentitiesByErgonodeSkus([$source->sku])[0] ?? null;
        $identityMode = $identity?->getIdentityMode() ?? $this->magentoSkuResolver->getConfiguredMode();
        if ($identityMode === ProductIdentityInterface::MODE_MAPPED) {
            $target = $this->targetResolver->resolve($source->sku, null, $identityMode);
            if ($target === null) {
                throw new LocalizedException(__(
                    'Ergonode product "%1" has no Magento product matched by the identity attribute.',
                    $source->sku
                ));
            }
            $this->magentoSkuSynchronizer->validate($target['product_id'], $target['sku'], $source->sku);
            return new ResolvedProductTarget($identityMode, $target['sku'], $target);
        }
        $magentoSku = $this->magentoSkuResolver->resolve($source, $identityMode);
        $target = $this->targetResolver->resolve($source->sku, $magentoSku, $identityMode);
        if ($target !== null) {
            $this->magentoSkuSynchronizer->validate($target['product_id'], $magentoSku, $source->sku);
        }

        return new ResolvedProductTarget($identityMode, $magentoSku, $target);
    }
}
