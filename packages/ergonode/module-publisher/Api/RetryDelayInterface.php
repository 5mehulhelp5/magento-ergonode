<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api;

interface RetryDelayInterface
{
    /**
     * @param int $seconds
     * @return void
     */
    public function wait(int $seconds): void;
}
