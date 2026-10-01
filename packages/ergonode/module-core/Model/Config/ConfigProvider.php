<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Config;

use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigProvider
{
    public const string ENVIRONMENT_TEST = 'test';
    public const string ENVIRONMENT_PRODUCTION = 'production';
    public const string XML_PATH_ENVIRONMENT = 'ergonode_connection/general/environment';
    public const string XML_PATH_MODE = 'ergonode_connection/general/mode';
    public const string XML_PATH_ENABLED = 'ergonode_connection/general/enabled';
    public const string XML_PATH_GRAPHQL_URL = 'ergonode_connection/test/url';
    public const string XML_PATH_PRODUCTION_GRAPHQL_URL = 'ergonode_connection/production/url';
    public const string XML_PATH_REQUESTS_PER_MINUTE = 'ergonode_connection/test/requests_per_minute';
    public const string XML_PATH_PRODUCTION_REQUESTS_PER_MINUTE = 'ergonode_connection/production/requests_per_minute';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ConnectionModePool $modePool
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED) && $this->modePool->has($this->getMode());
    }

    public function allowsWrites(): bool
    {
        return $this->isEnabled() && $this->modePool->get($this->getMode())->allowsWrites();
    }

    public function getGraphQlUrl(): string
    {
        $path = $this->getEnvironment() === self::ENVIRONMENT_PRODUCTION
            ? self::XML_PATH_PRODUCTION_GRAPHQL_URL
            : self::XML_PATH_GRAPHQL_URL;

        return trim((string)$this->scopeConfig->getValue($path));
    }

    public function getRequestsPerMinute(?string $environment = null): int
    {
        $environment = $environment ?? $this->getEnvironment();
        $this->validateEnvironment($environment);
        $path = $environment === self::ENVIRONMENT_PRODUCTION
            ? self::XML_PATH_PRODUCTION_REQUESTS_PER_MINUTE
            : self::XML_PATH_REQUESTS_PER_MINUTE;

        return max(0, (int)$this->scopeConfig->getValue($path));
    }

    public function getApiKey(): string
    {
        if (!$this->isEnabled()) {
            throw new ConnectionConfigurationException(
                (string)__('The active Ergonode connection is disabled or its mode is unavailable.'),
                ConnectionConfigurationException::FAILURE_AUTHORIZATION
            );
        }

        return $this->modePool->get($this->getMode())->getApiKey($this->getEnvironment());
    }

    public function getMode(): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_MODE));
    }

    public function getEnvironment(): string
    {
        $environment = trim((string)$this->scopeConfig->getValue(self::XML_PATH_ENVIRONMENT));
        $this->validateEnvironment($environment);

        return $environment;
    }

    private function validateEnvironment(string $environment): void
    {
        if (!in_array($environment, [self::ENVIRONMENT_TEST, self::ENVIRONMENT_PRODUCTION], true)) {
            throw new ConnectionConfigurationException(
                (string)__('Choose a valid Ergonode environment.'),
                ConnectionConfigurationException::FAILURE_REQUEST_CONSTRUCTION
            );
        }
    }
}
