<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Readiness;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttributeConsumer\Model\Product\SkuIdentityMappingResolver;
use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;
use Magento\Framework\Exception\LocalizedException;

class SkuIdentityMappingCheck implements ReadinessCheckInterface
{
    public function __construct(
        private readonly ProductAttributePolicy $attributePolicy,
        private readonly SkuIdentityMappingResolver $mappingResolver,
        private readonly ReadinessIssueFactoryInterface $issueFactory
    ) {
    }

    public function getCode(): string
    {
        return 'attributes.sku_identity_mapping';
    }

    public function getDomain(): string
    {
        return 'attributes';
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
        if (!$this->attributePolicy->isMappingRequired('sku')) {
            return [];
        }
        try {
            $this->mappingResolver->resolve();
        } catch (LocalizedException $exception) {
            return [$this->issueFactory->create(
                'attributes.sku_identity_mapping_invalid',
                $this->getDomain(),
                ReadinessIssueInterface::SEVERITY_BLOCKER,
                $exception->getMessage(),
                [],
                'attributes'
            )];
        }

        return [];
    }
}
