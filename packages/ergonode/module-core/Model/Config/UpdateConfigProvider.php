<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Config;

use Ergonode\Core\Api\UpdateConfigurationProviderInterface;

class UpdateConfigProvider implements UpdateConfigurationProviderInterface
{
    public function __construct(private readonly ConfigProvider $configProvider)
    {
    }

    public function isEnabled(): bool
    {
        return $this->configProvider->allowsWrites();
    }

    public function getApiKey(): string
    {
        return $this->isEnabled() ? $this->configProvider->getApiKey() : '';
    }
}
