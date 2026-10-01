<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Rest;

use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Framework\Exception\LocalizedException;

class ConnectionContext
{
    public function __construct(private readonly ConfigProvider $config)
    {
    }

    public function profile(): string
    {
        return $this->config->getEnvironment();
    }

    public function assertAvailable(): void
    {
        if (!$this->config->allowsWrites()) {
            throw new LocalizedException(__('Enable the Ergonode write connection before using REST.'));
        }
    }

    public function origin(): string
    {
        $parts = parse_url($this->config->getGraphQlUrl());
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
        ) {
            throw new LocalizedException(__('Configure a valid HTTPS Ergonode URL.'));
        }
        return 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
