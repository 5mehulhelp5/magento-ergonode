<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Model;

use Ergonode\ProductAdminUi\Api\ProductSelectionInterface;
use Ergonode\Product\Api\ProductCatalogInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class ProductSelection implements ProductSelectionInterface
{
    public function __construct(
        private readonly ProductCatalogInterface $catalog,
        private readonly Json $json
    ) {
    }

    /** @return int[] */
    public function snapshot(string $payload): array
    {
        $selection = $this->json->unserialize($payload);
        if (!is_array($selection) || !is_bool($selection['all'] ?? null)
            || !is_string($selection['search'] ?? null)
            || !is_array($selection['criteria'] ?? [])
        ) {
            throw new LocalizedException(__('Invalid product selection.'));
        }
        $ids = $this->catalog->getIds(
            $selection['search'],
            $this->positiveIds($selection['selected'] ?? null),
            $this->positiveIds($selection['excluded'] ?? null),
            $selection['all'],
            $selection['criteria'] ?? []
        );
        if ($ids === []) {
            throw new LocalizedException(__('Choose at least one product.'));
        }

        return $ids;
    }

    public function decodeIds(string $payload): array
    {
        return $this->positiveIds($this->json->unserialize($payload));
    }

    /** @return int[] */
    private function positiveIds(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new LocalizedException(__('Invalid product identifiers.'));
        }
        foreach ($values as $value) {
            if (!is_int($value) || $value < 1) {
                throw new LocalizedException(__('Invalid product identifiers.'));
            }
        }

        return array_values(array_unique($values));
    }
}
