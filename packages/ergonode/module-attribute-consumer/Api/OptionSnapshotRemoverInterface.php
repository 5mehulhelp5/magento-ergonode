<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface OptionSnapshotRemoverInterface
{
    /**
     * @param string $attributeCode
     * @param string $optionCode
     * @return void
     * @throws LocalizedException
     */
    public function remove(string $attributeCode, string $optionCode): void;
}
