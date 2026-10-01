<?php

declare(strict_types=1);

namespace Ergonode\Core\Api\Exception;

interface RetryAfterExceptionInterface
{
    /** @return string */
    public function getMessage(): string;

    /** @return int|null */
    public function getRetryAfterSeconds(): ?int;
}
