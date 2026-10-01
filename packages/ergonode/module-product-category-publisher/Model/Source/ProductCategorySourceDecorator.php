<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Source;

use Ergonode\ProductCategoryPublisher\Model\Data\ProductCategoryState;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\ProductSourceDecoratorInterface;

class ProductCategorySourceDecorator implements ProductSourceDecoratorInterface
{
    public function __construct(private readonly MagentoProductCategorySource $categorySource)
    {
    }

    public function decorate(array $states, array $products): array
    {
        $stateSkus = array_fill_keys(array_map(
            static fn (ProductStateInterface $state): string => $state->getSku(),
            $states
        ), true);
        $categoryStates = $this->categorySource->load(array_intersect_key($products, $stateSkus));
        $decorated = [];
        foreach ($states as $state) {
            $categoryState = $categoryStates[$state->getSku()] ?? ['codes' => [], 'authoritative' => true];
            $decorated[] = new ProductCategoryState(
                $state,
                $categoryState['codes'],
                $categoryState['authoritative']
            );
        }

        return $decorated;
    }
}
