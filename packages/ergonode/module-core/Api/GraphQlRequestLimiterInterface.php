<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Ergonode\Core\Api\Exception\GraphQlRequestException;

interface GraphQlRequestLimiterInterface
{
    /**
     * Reserve one outbound HTTP request from the environment minute quota, unless disabled.
     *
     * @param string|null $environment Explicit environment for tests; null selects the active environment.
     * @return void
     * @throws GraphQlRequestException
     */
    public function throttle(?string $environment = null): void;
}
