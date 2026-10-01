<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Provider;

use Ergonode\CategoryConsumer\Api\CategoryTreeSyncMetadataProviderInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\Core\Model\Import\CursorStorage;

class CategoryTreeSyncMetadataProvider implements CategoryTreeSyncMetadataProviderInterface
{
    public function __construct(
        private readonly CursorStorage $cursorStorage
    ) {
    }

    public function get(): array
    {
        $cursorState = $this->cursorStorage->get(CategoryTreeStreamImporter::PROCESS_CODE);

        return [
            'cursor' => $cursorState['cursor'] ?? null,
            'synced_at' => $cursorState['synced_at'] ?? null,
        ];
    }
}
