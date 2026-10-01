<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;
use Ergonode\Core\Model\Config\ConfigProvider;

class ConnectionConfigurationCheck implements ReadinessCheckInterface
{
    private const string DOMAIN = 'connection';

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ReadinessIssueFactoryInterface $issueFactory
    ) {
    }

    public function getCode(): string
    {
        return 'connection.configuration';
    }

    public function getDomain(): string
    {
        return self::DOMAIN;
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, [
            ReadinessContextInterface::OPERATION_OVERVIEW,
            ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS,
        ], true);
    }

    public function check(ReadinessContextInterface $context): array
    {
        $issues = [];
        if ($this->configProvider->getGraphQlUrl() === '') {
            $issues[] = $this->blocker(
                'connection.graphql_url_missing',
                (string)__('Configure the Ergonode GraphQL URL.')
            );
        }
        if ($context->getOperation() === ReadinessContextInterface::OPERATION_OVERVIEW) {
            if (!$this->configProvider->isEnabled()) {
                $issues[] = $this->blocker(
                    'connection.unavailable',
                    (string)__('Enable the active Ergonode connection and choose an available operating mode.')
                );
            }
            if ($this->configProvider->isEnabled() && $this->configProvider->getApiKey() === '') {
                $issues[] = $this->blocker(
                    'connection.api_key_missing',
                    (string)__('Configure the API key for the active environment and operating mode.')
                );
            }
        }
        return $issues;
    }

    private function blocker(string $code, string $message): ReadinessIssueInterface
    {
        return $this->issueFactory->create(
            $code,
            self::DOMAIN,
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            $message,
            remediation: 'connection'
        );
    }
}
