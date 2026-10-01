<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface AttributeSnapshotRemoverInterface
{
    /**
     * @param string $attributeCode
     * @return void
     * @throws LocalizedException
     */
    public function remove(string $attributeCode): void;
}
