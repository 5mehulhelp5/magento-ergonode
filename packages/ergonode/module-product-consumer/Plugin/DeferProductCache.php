<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Plugin;

use Ergonode\ProductConsumer\Model\Pipeline\BatchScope;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Manager;

/** Only native signals for products in the active import are deferred. Other saves keep Magento behavior. */
class DeferProductCache
{
    public function __construct(private readonly BatchScope $scope)
    {
    }

    public function aroundCleanModelCache(Product $subject, callable $proceed): mixed
    {
        return $this->shouldDefer($subject) ? $subject : $proceed();
    }

    private function shouldDefer(Product $product): bool
    {
        $context = $this->scope->get();
        if ($context?->current !== null && $context->current->productId === null && (int)$product->getId() > 0) {
            $this->scope->recordProduct((int)$product->getId());
        }
        return $this->scope->contains((int)$product->getId());
    }

    public function aroundDispatch(Manager $subject, callable $proceed, $eventName, array $data = []): mixed
    {
        $object = $data['object'] ?? null;
        if ($eventName === 'clean_cache_by_tags' && $object instanceof Product
            && $this->shouldDefer($object)) {
            return null;
        }
        return $proceed($eventName, $data);
    }
}
