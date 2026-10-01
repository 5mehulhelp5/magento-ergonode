<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

interface CategoryRemoteIdentityProviderInterface
{
    /**
     * @param int $categoryTreeId
     * @param string[] $codes
     * @return array<string, string>
     */
    public function getIdsByCode(int $categoryTreeId, array $codes): array;
}
