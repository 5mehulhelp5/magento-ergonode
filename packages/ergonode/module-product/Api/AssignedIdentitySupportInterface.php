<?php

declare(strict_types=1);

namespace Ergonode\Product\Api;

interface AssignedIdentitySupportInterface
{
    /**
     * Validate the extension's mapping required for independent Ergonode SKUs.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(): void;
}
