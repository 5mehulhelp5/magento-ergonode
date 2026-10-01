<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use InvalidArgumentException;

final readonly class ProductRelationState implements ProductRelationStateInterface
{
    /** @var string[] */
    private array $bindingCodes;

    /** @var string[] */
    private array $variantSkus;

    /** @var array<string, int> */
    private array $groupedChildren;

    /**
     * @param string[] $bindingCodes
     * @param string[] $variantSkus
     * @param array<string, int> $groupedChildren
     */
    public function __construct(array $bindingCodes = [], array $variantSkus = [], array $groupedChildren = [])
    {
        $this->bindingCodes = $this->normalizeStrings($bindingCodes, 'Binding code');
        $this->variantSkus = $this->normalizeStrings($variantSkus, 'Variant SKU');
        $children = [];
        foreach ($groupedChildren as $sku => $quantity) {
            $sku = trim((string)$sku);
            if ($sku === '' || !is_int($quantity) || $quantity < 1) {
                throw new InvalidArgumentException(
                    'Grouped children require a non-empty SKU and positive integer quantity.'
                );
            }
            $children[$sku] = $quantity;
        }
        ksort($children);
        $this->groupedChildren = $children;
    }

    public function getBindingCodes(): array
    {
        return $this->bindingCodes;
    }

    public function getVariantSkus(): array
    {
        return $this->variantSkus;
    }

    public function getGroupedChildren(): array
    {
        return $this->groupedChildren;
    }

    /** @param string[] $values @return string[] */
    private function normalizeStrings(array $values, string $label): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException($label . ' cannot be empty.');
            }
            $normalized[] = trim($value);
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
