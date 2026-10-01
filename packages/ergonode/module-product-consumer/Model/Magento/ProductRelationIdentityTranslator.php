<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductRelationIdentityTranslator
{
    public function __construct(
        private readonly ProductIdentityServiceInterface $identityService
    ) {
    }

    /** @param string[] $ergonodeSkus @return string[] */
    public function toMagentoSkus(array $ergonodeSkus): array
    {
        $ergonodeSkus = array_values(array_unique(array_filter(array_map('trim', $ergonodeSkus))));
        $identities = [];
        foreach ($this->identityService->getIdentitiesByErgonodeSkus($ergonodeSkus) as $identity) {
            $identities['sku:' . $identity->getErgonodeSku()] = $identity;
        }
        $result = [];
        foreach ($ergonodeSkus as $ergonodeSku) {
            $identity = $identities['sku:' . $ergonodeSku] ?? null;
            if ($identity !== null) {
                $result[] = $identity->getMagentoSku();
                continue;
            }
            throw new LocalizedException(__(
                'Related Ergonode product "%1" has no Magento product identity mapping.',
                $ergonodeSku
            ));
        }

        return $result;
    }
}
