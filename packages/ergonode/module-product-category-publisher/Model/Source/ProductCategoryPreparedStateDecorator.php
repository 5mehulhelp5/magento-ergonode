<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Source;

use Ergonode\ProductCategoryPublisher\Api\Data\ProductCategoryStateInterface;
use Ergonode\ProductCategoryPublisher\Model\Data\ProductCategoryState;
use Ergonode\ProductPublisher\Api\Data\ProductStateDecoratorInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\ProductPreparedStateDecoratorInterface;

class ProductCategoryPreparedStateDecorator implements ProductPreparedStateDecoratorInterface
{
    public function decorate(
        ProductStateInterface $sourceState,
        ProductStateInterface $preparedState
    ): ProductStateInterface {
        $categoryState = $this->findCategoryState($sourceState);
        if ($categoryState === null) {
            return $preparedState;
        }

        return new ProductCategoryState(
            $preparedState,
            $categoryState->getCategoryCodes(),
            $categoryState->isCategorySourceAuthoritative()
        );
    }

    private function findCategoryState(?ProductStateInterface $state): ?ProductCategoryStateInterface
    {
        while ($state !== null) {
            if ($state instanceof ProductCategoryStateInterface) {
                return $state;
            }
            $state = $state instanceof ProductStateDecoratorInterface
                ? $state->getDecoratedProductState()
                : null;
        }

        return null;
    }
}
