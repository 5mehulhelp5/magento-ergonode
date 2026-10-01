<?php

declare(strict_types=1);

namespace Ergonode\Core\Api\Data;

interface ReadinessReportInterface
{
    public const string STATUS_READY = 'ready';

    public const string STATUS_WARNING = 'warning';

    public const string STATUS_BLOCKED = 'blocked';

    /**
     * Return all readiness issues in deterministic display order.
     *
     * @return ReadinessIssueInterface[]
     */
    public function getIssues(): array;

    /**
     * Return domains covered by at least one readiness check.
     *
     * @return string[]
     */
    public function getCheckedDomains(): array;

    /**
     * Return the aggregate readiness status.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Report whether at least one issue blocks the operation.
     *
     * @return bool
     */
    public function hasBlockers(): bool;
}
