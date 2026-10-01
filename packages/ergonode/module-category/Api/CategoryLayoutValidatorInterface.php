<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

interface CategoryLayoutValidatorInterface
{
    /**
     * Validate draft identities, Magento root membership and the resulting hierarchy before writes.
     *
     * @param int $categoryTreeId
     * @param array<int, array<string, mixed>> $items
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(int $categoryTreeId, array $items): void;
}
