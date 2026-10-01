<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Ergonode\Core\Api\Data\ReadinessReportInterface;

interface ReadinessEvaluatorInterface
{
    /**
     * @param string $operation
     * @param array<string, string[]> $entityIdentifiers
     * @return ReadinessReportInterface
     */
    public function evaluate(string $operation, array $entityIdentifiers = []): ReadinessReportInterface;
}
