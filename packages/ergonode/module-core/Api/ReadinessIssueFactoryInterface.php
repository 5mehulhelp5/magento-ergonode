<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;

interface ReadinessIssueFactoryInterface
{
    /**
     * @param string $code
     * @param string $domain
     * @param string $severity
     * @param string $message
     * @param string[] $details
     * @param string|null $remediation
     * @return ReadinessIssueInterface
     */
    public function create(
        string $code,
        string $domain,
        string $severity,
        string $message,
        array $details = [],
        ?string $remediation = null
    ): ReadinessIssueInterface;
}
