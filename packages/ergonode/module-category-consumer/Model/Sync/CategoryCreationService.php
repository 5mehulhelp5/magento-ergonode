<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryCreationDataProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;

class CategoryCreationService
{
    public function __construct(
        private readonly CategoryCreationDataProviderInterface $creationDataProvider,
        private readonly CategoryCreator $categoryCreator,
        private readonly CategoryEntitySynchronizerInterface $entitySynchronizer
    ) {
    }

    /**
     * @return array{id: int, parent_id: int, label: string, path: string, level: int, position: int, url_key: string}
     */
    public function create(string $categoryCode, string $label, int $parentId): array
    {
        $creationData = $this->creationDataProvider->get($categoryCode);
        $category = $this->categoryCreator->create($label, $parentId, $creationData['values']);
        $entity = $creationData['entity'];
        if ($entity !== null) {
            $this->entitySynchronizer->synchronize([[
                'category_id' => (int)$category['id'],
                'entity' => $entity,
            ]]);
        }

        return $category;
    }
}
