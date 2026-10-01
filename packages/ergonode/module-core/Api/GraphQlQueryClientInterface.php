<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Magento\Framework\Exception\LocalizedException;

interface GraphQlQueryClientInterface
{
    /**
     * @param string $document
     * @param array<string, mixed> $variables
     * @param bool $requireEnabled
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function query(string $document, array $variables = [], bool $requireEnabled = true): array;
}
