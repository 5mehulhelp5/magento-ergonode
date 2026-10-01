<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Snapshot;

use Ergonode\AttributeConsumer\Api\AttributeSnapshotRemoverInterface;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AttributeSnapshotRemover implements AttributeSnapshotRemoverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly OptionSnapshotCache $snapshotCache,
        private readonly ErgonodeAttributeProvider $attributeProvider
    ) {
    }

    public function remove(string $attributeCode): void
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            throw new LocalizedException(__('Ergonode attribute code is required.'));
        }

        $this->snapshotCache->reset();
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $connection->delete(
                $this->resourceConnection->getTableName('ergonode_attribute_option'),
                ['attribute_code = ?' => $attributeCode]
            );
            $deleted = $connection->delete(
                $this->resourceConnection->getTableName('ergonode_attribute'),
                ['code = ?' => $attributeCode]
            );
            if ($deleted !== 1) {
                throw new LocalizedException(
                    __('Attribute "%1" is not available in the local snapshot.', $attributeCode)
                );
            }
            $connection->commit();
            $this->attributeProvider->reset();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
