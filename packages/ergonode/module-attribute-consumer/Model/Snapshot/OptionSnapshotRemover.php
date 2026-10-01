<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Snapshot;

use Ergonode\AttributeConsumer\Api\OptionSnapshotRemoverInterface;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class OptionSnapshotRemover implements OptionSnapshotRemoverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly OptionSnapshotCache $snapshotCache
    ) {
    }

    public function remove(string $attributeCode, string $optionCode): void
    {
        $attributeCode = trim($attributeCode);
        $optionCode = trim($optionCode);
        if ($attributeCode === '' || $optionCode === '') {
            throw new LocalizedException(__('Ergonode attribute and option codes are required.'));
        }

        $this->snapshotCache->reset();
        $deleted = $this->resourceConnection->getConnection()->delete(
            $this->resourceConnection->getTableName('ergonode_attribute_option'),
            ['attribute_code = ?' => $attributeCode, 'option_code = ?' => $optionCode]
        );
        if ($deleted !== 1) {
            throw new LocalizedException(__('Option "%1" is not available in the local snapshot.', $optionCode));
        }
    }
}
