<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Snapshot;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Api\AttributeSnapshotRefreshInterface;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionSnapshotInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class AttributeSnapshotRefresh implements AttributeSnapshotRefreshInterface
{
    private const string LOCK_NAME = 'ergonode_attribute_synchronization_batch';

    public function __construct(
        private readonly AttributeDefinitionSynchronizationInterface $definitionSynchronization,
        private readonly AttributeDefinitionSnapshotInterface $snapshot,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    public function refreshSnapshot(?string $cursor = null, ?int $pageSize = null): array
    {
        if ($cursor === null) {
            $this->definitionSynchronization->synchronize(true);
        }
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Attribute synchronization is already running.'));
        }

        try {
            return $this->snapshot->page($cursor, $pageSize);
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }
}
