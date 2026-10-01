<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

class ProductAttributeValueResolverPool
{
    /** @param ProductAttributeValueResolverInterface[] $resolvers */
    public function __construct(private readonly array $resolvers = [])
    {
    }

    /** @param array<string, mixed> $mapping */
    public function get(array $mapping): ?ProductAttributeValueResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($mapping)) {
                return $resolver;
            }
        }

        return null;
    }

    public function resetResolutionScope(): void
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof ProductAttributeResolutionScopeInterface) {
                $resolver->resetResolutionScope();
            }
        }
    }
}
