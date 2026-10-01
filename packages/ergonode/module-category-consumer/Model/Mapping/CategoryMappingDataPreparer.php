<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Mapping;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Magento\Framework\Exception\LocalizedException;

class CategoryMappingDataPreparer
{
    public function __construct(
        private readonly CategoryEntityLoaderInterface $loader,
        private readonly CategoryEntitySynchronizerInterface $synchronizer,
        private readonly CategoryConfigProvider $config
    ) {
    }

    public function hasWork(): bool
    {
        return $this->config->isDataSynchronizationEnabled()
            && (!$this->synchronizer instanceof CategoryDataWorkProviderInterface || $this->synchronizer->hasWork());
    }

    /**
     * Fetch before entering a database transaction. No catalog values are written here.
     * @param array<string, int> $mappings
     * @return list<array{category_id: int, entity: array<string, mixed>}>
     */
    public function prepare(array $mappings): array
    {
        if ($mappings === [] || !$this->hasWork()) {
            return [];
        }
        $entities = $this->loader->loadMany(array_map('strval', array_keys($mappings)));
        $operations = [];
        foreach ($mappings as $code => $categoryId) {
            $entity = $entities[$code] ?? null;
            if ($entity === null || $entity['code'] !== (string)$code) {
                throw new LocalizedException(__('Mapped Ergonode category "%1" no longer exists.', $code));
            }
            $operations[] = ['category_id' => $categoryId, 'entity' => $entity];
        }

        return $operations;
    }
}
