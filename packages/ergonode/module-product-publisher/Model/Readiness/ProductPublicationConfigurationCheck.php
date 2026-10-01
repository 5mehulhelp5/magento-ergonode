<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;
use Ergonode\Core\Api\UpdateConfigurationProviderInterface;

class ProductPublicationConfigurationCheck implements ReadinessCheckInterface
{
    public function __construct(
        private readonly UpdateConfigurationProviderInterface $updateConfiguration,
        private readonly ReadinessIssueFactoryInterface $issueFactory
    ) {
    }

    public function getCode(): string
    {
        return 'product.publisher_configuration';
    }

    public function getDomain(): string
    {
        return 'connection';
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
        if (!$this->updateConfiguration->isEnabled()) {
            $issues[] = $this->issue(
                $context,
                'products.write_operations_disabled',
                (string)__('Enable Ergonode write operations before publishing products.'),
                'connection'
            );
        }
        if ($this->updateConfiguration->getApiKey() === '') {
            $issues[] = $this->issue(
                $context,
                'products.write_api_key_missing',
                (string)__('Configure the Ergonode write API key before publishing products.'),
                'connection'
            );
        }
        return $issues;
    }

    private function issue(
        ReadinessContextInterface $context,
        string $code,
        string $message,
        string $remediation = 'products'
    ): ReadinessIssueInterface {
        return $this->issueFactory->create(
            $code,
            $this->getDomain(),
            $context->getOperation() === ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS
                ? ReadinessIssueInterface::SEVERITY_BLOCKER
                : ReadinessIssueInterface::SEVERITY_WARNING,
            $message,
            remediation: $remediation
        );
    }
}
