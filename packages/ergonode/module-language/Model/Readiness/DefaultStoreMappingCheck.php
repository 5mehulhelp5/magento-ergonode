<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class DefaultStoreMappingCheck implements ReadinessCheckInterface
{
    public function __construct(
        private readonly LanguageStoreMappingProviderInterface $mappingProvider,
        private readonly ReadinessIssueFactoryInterface $issueFactory
    ) {
    }

    public function getCode(): string
    {
        return 'language.default_store_mapping';
    }

    public function getDomain(): string
    {
        return 'languages';
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
        if ($this->mappingProvider->getAdminLanguageCode() !== null) {
            return [];
        }

        return [$this->issueFactory->create(
            'language.default_store_mapping_missing',
            $this->getDomain(),
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            (string)__(
                'Map Magento Default Values (store ID 0) to an active Ergonode language.'
            ),
            remediation: 'languages'
        )];
    }
}
