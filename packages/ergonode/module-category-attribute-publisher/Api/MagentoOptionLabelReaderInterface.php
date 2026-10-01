<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Api;

interface MagentoOptionLabelReaderInterface
{
    /**
     * Read persisted option labels keyed by option ID and store ID, including the default store.
     *
     * @param int $attributeId
     * @return array<int, array<int, string>>
     */
    public function read(int $attributeId): array;
}
