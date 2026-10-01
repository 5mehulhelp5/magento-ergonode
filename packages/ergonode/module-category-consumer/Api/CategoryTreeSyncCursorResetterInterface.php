<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryTreeSyncCursorResetterInterface
{
    /** @return void */
    public function reset(): void;
}
