<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Model;

use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;

class CategorySynchronizationResponse
{
    public function __construct(private readonly CategoryTreeMappingUiProvider $mappingUiProvider)
    {
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function format(array $state): array
    {
        $state['success'] = $state['state'] === 'success';
        $state['completed'] = in_array($state['state'], ['success', 'warning'], true);
        if (in_array($state['state'], ['success', 'warning', 'paused', 'error'], true)) {
            $state['config'] = $this->mappingUiProvider->getConfig(true);
        }

        return $state;
    }
}
