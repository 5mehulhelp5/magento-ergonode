<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Readiness;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;

class RequiredAttributeMappingCheck implements ReadinessCheckInterface
{
    public function __construct(
        private readonly MagentoAttributeProvider $attributeProvider,
        private readonly ProductAttributeMappingProviderInterface $mappingProvider,
        private readonly ReadinessIssueFactoryInterface $issueFactory
    ) {
    }

    public function getCode(): string
    {
        return 'attributes.required_mapping';
    }

    public function getDomain(): string
    {
        return 'attributes';
    }

    public function supports(string $operation): bool
    {
        return in_array(
            $operation,
            [
            ReadinessContextInterface::OPERATION_OVERVIEW,
            ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS,
            ],
            true
        );
    }

    public function check(ReadinessContextInterface $context): array
    {
        $mappedCodes = array_fill_keys(
            array_map(
                static fn (array $mapping): string => (string)$mapping['magento_attribute_code'],
                $this->mappingProvider->getMappings()
            ),
            true
        );
        $missing = [];
        foreach ($this->attributeProvider->getAttributes() as $attribute) {
            if (!empty($attribute['required']) && !isset($mappedCodes[$attribute['code']])) {
                $missing[] = sprintf('%s (%s)', $attribute['label'], $attribute['code']);
            }
        }
        if ($missing === []) {
            return [];
        }

        sort($missing);

        return [$this->issueFactory->create(
            'attributes.required_mapping_missing',
            $this->getDomain(),
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            (string)__(
                '%1 required Magento product attribute(s) do not have a complete Ergonode mapping.',
                count($missing)
            ),
            $missing,
            'attributes'
        )];
    }
}
