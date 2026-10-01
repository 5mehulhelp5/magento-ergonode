<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;

interface ProductPreparedStateDecoratorInterface
{
    /**
     * Reapply optional domain state after native Ergonode identities and product references are resolved.
     *
     * @param ProductStateInterface $sourceState
     * @param ProductStateInterface $preparedState
     * @return ProductStateInterface
     */
    public function decorate(
        ProductStateInterface $sourceState,
        ProductStateInterface $preparedState
    ): ProductStateInterface;
}
