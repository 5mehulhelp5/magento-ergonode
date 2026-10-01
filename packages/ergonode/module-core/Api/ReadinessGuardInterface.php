<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Ergonode\Core\Exception\ReadinessGuardException;

interface ReadinessGuardInterface
{
    /**
     * @param string $operation
     * @param array<string, string[]> $entityIdentifiers
     * @return void
     * @throws ReadinessGuardException
     */
    public function assertReady(string $operation, array $entityIdentifiers = []): void;
}
