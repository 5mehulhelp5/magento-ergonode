<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Snapshot;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeSnapshotRemoverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeSnapshotRemover implements CategoryAttributeSnapshotRemoverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function remove(string $attributeCode): void
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            throw new LocalizedException(__('Ergonode category attribute code is required.'));
        }

        $deleted = $this->resourceConnection->getConnection()->delete(
            $this->resourceConnection->getTableName('ergonode_category_attribute'),
            ['attribute_code = ?' => $attributeCode]
        );
        if ($deleted !== 1) {
            throw new LocalizedException(
                __('Category attribute "%1" is not available in the local snapshot.', $attributeCode)
            );
        }
    }
}
