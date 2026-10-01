<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\MappedCategoryAttributeSynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Magento\Framework\Exception\LocalizedException;

class MappedCategoryAttributeSynchronizer implements
    MappedCategoryAttributeSynchronizerInterface,
    CategoryDataWorkProviderInterface
{
    public function __construct(
        private readonly CategoryConfigProvider $configProvider,
        private readonly CategoryEntityLoaderInterface $entityLoader,
        private readonly CategoryEntitySynchronizerInterface $entitySynchronizer
    ) {
    }

    public function hasWork(): bool
    {
        return $this->configProvider->isDataSynchronizationEnabled()
            && (!$this->entitySynchronizer instanceof CategoryDataWorkProviderInterface
                || $this->entitySynchronizer->hasWork());
    }

    /** @return array{snapshot: string, attributes: int} */
    public function synchronize(string $ergonodeCode, int $magentoCategoryId): array
    {
        $ergonodeCode = trim($ergonodeCode);
        if ($ergonodeCode === '' || $magentoCategoryId <= 0) {
            throw new LocalizedException(__('Mapped Ergonode and Magento categories are required.'));
        }
        if (!$this->configProvider->isDataSynchronizationEnabled()) {
            return ['snapshot' => 'unchanged', 'attributes' => 0];
        }

        $entity = $this->entityLoader->load($ergonodeCode);
        if ($entity === null) {
            throw new LocalizedException(__('Mapped Ergonode category "%1" no longer exists.', $ergonodeCode));
        }
        if ((string)$entity['code'] !== $ergonodeCode) {
            throw new LocalizedException(__('Ergonode returned an unexpected category code "%1".', $entity['code']));
        }

        $stats = $this->entitySynchronizer->synchronize([[
            'category_id' => $magentoCategoryId,
            'entity' => $entity,
        ]]);

        return [
            'snapshot' => (string)($stats['snapshot_statuses'][$ergonodeCode] ?? 'unchanged'),
            'attributes' => $stats['attributes'],
        ];
    }
}
