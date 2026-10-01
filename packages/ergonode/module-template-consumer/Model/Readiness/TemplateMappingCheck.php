<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;
use Ergonode\Template\Api\TemplateAttributeSetMappingProviderInterface;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;

class TemplateMappingCheck implements ReadinessCheckInterface
{
    private const string PRODUCT_DOMAIN = 'products';

    public function __construct(
        private readonly TemplateAttributeSetMappingProviderInterface $mappingProvider,
        private readonly TemplateCacheProvider $templateProvider,
        private readonly ProductAttributeSetUsageProvider $usageProvider,
        private readonly ReadinessIssueFactoryInterface $issueFactory
    ) {
    }

    public function getCode(): string
    {
        return 'templates.attribute_set_mapping';
    }

    public function getDomain(): string
    {
        return 'templates';
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
        $selectedSkus = $context->getOperation() === ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS
            ? $context->getEntityIdentifiers(self::PRODUCT_DOMAIN)
            : [];
        $usage = $this->usageProvider->getUsage($selectedSkus);
        $issues = [];
        if ($this->templateProvider->getTemplatesWithAttributeSet() === []) {
            $issues[] = $this->blocker(
                'templates.mapping_missing',
                (string)__('Map at least one Ergonode template to a Magento attribute set.')
            );
        }
        if ($usage === []) {
            return $issues;
        }

        $mapped = $this->mappingProvider->getTemplateCodesByAttributeSetIds(array_keys($usage));
        foreach ($usage as $attributeSetId => $productCount) {
            if (!isset($mapped[$attributeSetId])) {
                $issues[] = $this->warning(
                    'templates.product_attribute_set_mapping_missing',
                    (string)__(
                        'Attribute set ID %1 (%2 product(s)) does not have an Ergonode template mapping. '
                            . 'Products using this attribute set will be skipped.',
                        $attributeSetId,
                        $productCount
                    )
                );
            }
        }
        return $issues;
    }

    /** @param string[] $details */
    private function blocker(string $code, string $message, array $details = []): ReadinessIssueInterface
    {
        return $this->issueFactory->create(
            $code,
            $this->getDomain(),
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            $message,
            $details,
            'templates'
        );
    }

    private function warning(string $code, string $message): ReadinessIssueInterface
    {
        return $this->issueFactory->create(
            $code,
            $this->getDomain(),
            ReadinessIssueInterface::SEVERITY_WARNING,
            $message,
            [],
            'templates'
        );
    }
}
