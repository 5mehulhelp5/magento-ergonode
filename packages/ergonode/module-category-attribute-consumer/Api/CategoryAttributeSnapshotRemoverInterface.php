<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategoryAttributeSnapshotRemoverInterface
{
    /**
     * @param string $attributeCode
     * @return void
     * @throws LocalizedException
     */
    public function remove(string $attributeCode): void;
}
