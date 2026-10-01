<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Magento\Framework\Exception\LocalizedException;

interface GraphQlWriteScopeQueryClientInterface
{
    /**
     * Execute a read-only query using the credential that authorizes writes.
     *
     * @param string $document
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function queryWriteScope(string $document, array $variables = []): array;
}
