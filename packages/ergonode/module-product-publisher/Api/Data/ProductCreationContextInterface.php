<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

/**
 * Optional creation context carried between identity preparation and publication.
 */
interface ProductCreationContextInterface extends ProductStateInterface
{
    /** @return bool */
    public function wasCreatedInCurrentSynchronization(): bool;
}
