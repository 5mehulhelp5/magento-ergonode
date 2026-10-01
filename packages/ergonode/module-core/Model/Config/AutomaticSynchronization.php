<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Config;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Model\GraphQl\Client;

class AutomaticSynchronization implements AutomaticSynchronizationInterface
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly Client $client
    ) {
    }
    public function isAllowed(): bool
    {
        return $this->config->getMode() === 'read'
            && $this->config->isEnabled()
            && $this->client->isConnectionAvailable();
    }
}
