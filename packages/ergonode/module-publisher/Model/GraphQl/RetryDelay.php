<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\GraphQl;

use Ergonode\Publisher\Api\RetryDelayInterface;

class RetryDelay implements RetryDelayInterface
{
    public function wait(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
