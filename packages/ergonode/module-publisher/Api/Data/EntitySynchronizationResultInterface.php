<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Data;

interface EntitySynchronizationResultInterface extends SynchronizationResultInterface
{
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_NOOP = 'noop';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_UNSUPPORTED = 'unsupported';
    public const string STATUS_CONFLICT = 'conflict';

    public const string REFERENCE_PRESENT = 'present';
    public const string REFERENCE_ABSENT = 'absent';
    public const string REFERENCE_UNKNOWN = 'unknown';

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return string
     */
    public function getReferenceStatus(): string;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;
}
