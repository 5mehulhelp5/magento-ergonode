<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Magento\Framework\App\ResourceConnection;

class OptionCacheReconciler
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly OptionSnapshotCache $snapshotCache
    ) {
    }

    /** @param string[] $currentOptionCodes */
    public function reconcile(string $attributeCode, array $currentOptionCodes): int
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            return 0;
        }

        $codes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $code): string => trim((string)$code),
            $currentOptionCodes
        ), static fn (string $code): bool => $code !== '')));
        $conditions = ['attribute_code = ?' => $attributeCode];
        if ($codes !== []) {
            $conditions['option_code NOT IN (?)'] = $codes;
        }

        $this->snapshotCache->reset();
        return $this->resourceConnection->getConnection()->delete(
            $this->resourceConnection->getTableName('ergonode_attribute_option'),
            $conditions
        );
    }
}
