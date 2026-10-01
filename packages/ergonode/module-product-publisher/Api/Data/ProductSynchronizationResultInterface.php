<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;

interface ProductSynchronizationResultInterface extends SynchronizationResultInterface
{
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_NOOP = 'noop';
    public const string STATUS_ATTENTION = 'attention';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_CONFLICT = 'conflict';
    public const string STATUS_BLOCKED_REFERENCE = 'blocked_reference';
    public const string STATUS_LOCAL_WARNING = 'local_warning';

    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;

    /** @return string[] Warnings attached only after confirmed successful publication. */
    public function getWarnings(): array;
}
