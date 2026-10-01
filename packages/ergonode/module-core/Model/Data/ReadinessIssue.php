<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Data;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use InvalidArgumentException;

final readonly class ReadinessIssue implements ReadinessIssueInterface
{
    /** @param string[] $details */
    public function __construct(
        private string $code,
        private string $domain,
        private string $severity,
        private string $message,
        private array $details = [],
        private ?string $remediation = null
    ) {
        if (trim($code) === '' || trim($domain) === '' || trim($message) === '') {
            throw new InvalidArgumentException('Readiness issue code, domain and message must not be empty.');
        }
        if (!in_array($severity, [self::SEVERITY_BLOCKER, self::SEVERITY_WARNING], true)) {
            throw new InvalidArgumentException('Unsupported readiness issue severity.');
        }
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getRemediation(): ?string
    {
        return $this->remediation;
    }
}
