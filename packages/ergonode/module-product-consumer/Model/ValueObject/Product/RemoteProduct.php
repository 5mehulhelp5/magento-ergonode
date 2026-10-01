<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ValueObject\Product;

use InvalidArgumentException;

final readonly class RemoteProduct
{
    /** @var RemoteProductAttribute[] */
    public array $attributes;

    /**
     * @param array<string, string> $statuses
     * @param RemoteProductAttribute[] $attributes
     * @param string[] $bindings
     * @param string[] $variants
     * @param array<string, int> $children
     */
    public function __construct(
        public string $sku,
        public string $type,
        public string $templateCode,
        public bool $isVariant,
        public array $statuses,
        array $attributes,
        public array $bindings = [],
        public array $variants = [],
        public array $children = []
    ) {
        if (trim($this->sku) === '') {
            throw new InvalidArgumentException('Remote product SKU cannot be empty.');
        }
        $byCode = [];
        foreach ($attributes as $attribute) {
            if (isset($byCode[$attribute->code])) {
                throw new InvalidArgumentException('Remote product attribute codes must be unique.');
            }
            $byCode[$attribute->code] = $attribute;
        }
        $this->attributes = array_values($byCode);
    }

    public function contentHash(): string
    {
        return hash('sha256', (string)json_encode($this->normalized(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     sku: string,
     *     type: string,
     *     template: string,
     *     is_variant: bool,
     *     statuses: array<string, string>,
     *     attributes: list<array{
     *         code: string,
     *         type: string,
     *         values: array<string, float|int|string|list<string>>
     *     }>,
     *     bindings: string[],
     *     variants: string[],
     *     children: array<string, int>
     * }
     */
    public function normalized(): array
    {
        $statuses = $this->statuses;
        $attributes = array_map(
            static fn (RemoteProductAttribute $attribute): array => $attribute->normalized(),
            $this->attributes
        );
        $bindings = $this->bindings;
        $variants = $this->variants;
        $children = $this->children;
        ksort($statuses);
        usort($attributes, static fn (array $left, array $right): int => $left['code'] <=> $right['code']);
        sort($bindings);
        sort($variants);
        ksort($children);

        return [
            'sku' => $this->sku,
            'type' => $this->type,
            'template' => $this->templateCode,
            'is_variant' => $this->isVariant,
            'statuses' => $statuses,
            'attributes' => $attributes,
            'bindings' => $bindings,
            'variants' => $variants,
            'children' => $children,
        ];
    }
}
