<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Data;

interface MutationVerificationResultInterface
{
    public const string STATUS_APPLIED = 'applied';
    public const string STATUS_NOT_APPLIED = 'not_applied';
    public const string STATUS_UNKNOWN = 'unknown';

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return mixed
     */
    public function getData(): mixed;
}
