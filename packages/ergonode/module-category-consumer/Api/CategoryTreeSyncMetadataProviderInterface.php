<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryTreeSyncMetadataProviderInterface
{
    /**
     * @return array{cursor: string|null, synced_at: string|null}
     */
    public function get(): array;
}
