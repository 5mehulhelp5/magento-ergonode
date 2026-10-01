<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Api\ProductAttributeValueResolverInterface;

class ProductAttributeValueResolverPool
{
    /** @param ProductAttributeValueResolverInterface[] $resolvers */
    public function __construct(
        private readonly ProductValueNormalizer $standardNormalizer,
        private readonly array $resolvers = []
    ) {
    }

    /**
     * @param array<string, mixed> $mapping
     * @return float|string|string[]|null
     */
    public function resolve(mixed $value, array $mapping, string $context): float|string|array|null
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($mapping)) {
                return $resolver->resolve($value, $mapping, $context);
            }
        }

        return $this->standardNormalizer->normalize(
            $value,
            (string)$mapping['ergonode_type'],
            (array)$mapping['option_ids'],
            $context
        );
    }
}
