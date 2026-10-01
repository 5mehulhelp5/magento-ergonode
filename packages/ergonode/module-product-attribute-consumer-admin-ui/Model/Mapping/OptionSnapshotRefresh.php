<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Model\Mapping;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class OptionSnapshotRefresh
{
    private const string LOCK_NAME = 'ergonode_option_synchronization';

    public function __construct(
        private readonly AttributeMappingProvider $mappingProvider,
        private readonly AttributeCacheRefresherInterface $cacheRefresher,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    /**
     * @return array{ergonode_attribute_code: string}
     */
    public function execute(int $attributeMappingId): array
    {
        $mapping = $this->mappingProvider->getMappingRow($attributeMappingId);
        $code = trim((string)($mapping['ergonode_attribute_code'] ?? ''));
        if ($code === '') {
            throw new LocalizedException(__('Options require a saved attribute mapping.'));
        }
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Option synchronization is already running.'));
        }

        try {
            $this->cacheRefresher->refreshOptions($code);

            return ['ergonode_attribute_code' => $code];
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }
}
