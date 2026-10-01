<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class ProductDeletionPolicy
{
    public function __construct(
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly ProductAttributeWriter $attributeWriter,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function deactivate(string $ergonodeSku): bool
    {
        $identity = $this->identityService->getIdentitiesByErgonodeSkus([$ergonodeSku])[0] ?? null;
        if ($identity === null) {
            return false;
        }
        $productId = $identity->getProductId();
        $storeIds = array_map('intval', array_keys($this->languageMappingProvider->getLanguageStoreMap()));
        $statuses = array_fill_keys($storeIds, Status::STATUS_DISABLED);
        $statuses[0] = Status::STATUS_DISABLED;
        $this->attributeWriter->write($productId, ['status' => $statuses]);
        $this->identityService->clearImportHash($ergonodeSku);

        return true;
    }
}
