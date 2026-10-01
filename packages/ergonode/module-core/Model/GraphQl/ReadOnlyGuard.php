<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl;

use Magento\Framework\Exception\LocalizedException;

class ReadOnlyGuard
{
    /**
     * @throws LocalizedException
     */
    public function assertQuery(string $document): void
    {
        $normalized = ltrim(preg_replace('/^\s*#.*$/m', '', $document) ?? $document);

        if (!preg_match('/^query\b/i', $normalized)) {
            throw new LocalizedException(__('Only read-only GraphQL query operations are allowed.'));
        }

        if (preg_match('/\bmutation\b/i', $normalized)) {
            throw new LocalizedException(__('GraphQL mutations are not allowed in this module.'));
        }
    }
}
