<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface UpdateConfigurationProviderInterface
{
    /** @return bool */
    public function isEnabled(): bool;

    /** @return string */
    public function getApiKey(): string;
}
