<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

/** Optional capability for synchronizers which can skip fetching unused data. */
interface CategoryDataWorkProviderInterface
{
    /** @return bool */
    public function hasWork(): bool;
}
