<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;
use Ergonode\Core\Model\Data\ReadinessIssue;

class ReadinessIssueFactory implements ReadinessIssueFactoryInterface
{
    public function create(
        string $code,
        string $domain,
        string $severity,
        string $message,
        array $details = [],
        ?string $remediation = null
    ): ReadinessIssueInterface {
        return new ReadinessIssue($code, $domain, $severity, $message, $details, $remediation);
    }
}
