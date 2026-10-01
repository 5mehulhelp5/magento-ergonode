<?php

declare(strict_types=1);

namespace Ergonode\Core\Api\Data;

interface ReadinessIssueInterface
{
    public const string SEVERITY_BLOCKER = 'blocker';

    public const string SEVERITY_WARNING = 'warning';

    /**
     * Return the stable machine-readable issue code.
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Return the owning readiness domain.
     *
     * @return string
     */
    public function getDomain(): string;

    /**
     * Return blocker or warning severity.
     *
     * @return string
     */
    public function getSeverity(): string;

    /**
     * Return the administrator-facing issue message.
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Return optional issue-specific details.
     *
     * @return string[]
     */
    public function getDetails(): array;

    /**
     * Return the semantic remediation destination, if available.
     *
     * @return string|null
     */
    public function getRemediation(): ?string;
}
