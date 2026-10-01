<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Data;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\Data\ReadinessReportInterface;
use InvalidArgumentException;

final readonly class ReadinessReport implements ReadinessReportInterface
{
    /**
     * @param ReadinessIssueInterface[] $issues
     * @param string[] $checkedDomains
     */
    public function __construct(
        private array $issues,
        private array $checkedDomains = []
    ) {
        foreach ($issues as $issue) {
            if (!$issue instanceof ReadinessIssueInterface) {
                throw new InvalidArgumentException('Readiness report accepts only readiness issues.');
            }
        }
    }

    public function getIssues(): array
    {
        return $this->issues;
    }

    public function getCheckedDomains(): array
    {
        return $this->checkedDomains;
    }

    public function getStatus(): string
    {
        if ($this->hasBlockers()) {
            return self::STATUS_BLOCKED;
        }

        return $this->issues === [] ? self::STATUS_READY : self::STATUS_WARNING;
    }

    public function hasBlockers(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->getSeverity() === ReadinessIssueInterface::SEVERITY_BLOCKER) {
                return true;
            }
        }

        return false;
    }
}
