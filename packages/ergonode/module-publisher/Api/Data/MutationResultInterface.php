<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Data;

interface MutationResultInterface
{
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_VALIDATION_FAILURE = 'validation_failure';
    public const string STATUS_PERMANENT_FAILURE = 'permanent_failure';
    public const string STATUS_TRANSIENT_FAILURE = 'transient_failure';
    public const string STATUS_UNRESOLVED = 'unresolved';

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return string
     */
    public function getAlias(): string;

    /**
     * @return MutationOperationInterface
     */
    public function getOperation(): MutationOperationInterface;

    /**
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getErrors(): array;

    /**
     * @return int
     */
    public function getAttempts(): int;
}
