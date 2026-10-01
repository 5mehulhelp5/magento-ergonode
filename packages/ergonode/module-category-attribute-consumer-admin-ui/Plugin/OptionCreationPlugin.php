<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Plugin;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\OptionMappingSaver;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeManagementProviderInterface;
use Ergonode\CategoryAttributeConsumer\Api\MagentoOptionCreatorInterface;
use Magento\Framework\Exception\LocalizedException;

class OptionCreationPlugin
{
    public function __construct(
        private readonly CategoryAttributeManagementProviderInterface $provider,
        private readonly MagentoOptionCreatorInterface $creator
    ) {
    }

    /** @param array<int, array<string, mixed>> $mappings
     * @return array{int, array<int, array<string, mixed>>}
     */
    public function beforePrepareMappings(OptionMappingSaver $subject, int $mappingId, array $mappings): array
    {
        $attribute = $this->provider->getAttributeMappingRow($mappingId);
        if (!$attribute || ($attribute['status'] ?? '') !== 'complete') {
            throw new LocalizedException(__('Options require a saved category attribute mapping.'));
        }
        foreach ($mappings as &$mapping) {
            $right = is_array($mapping['right'] ?? null) ? $mapping['right'] : [];
            if (!empty($right['pending_create'])) {
                $mapping['right'] = $this->creator->create(
                    (string)$attribute['magento_attribute_code'],
                    trim((string)($right['create_label'] ?? $mapping['left']['label'] ?? ''))
                );
            }
        }
        unset($mapping);
        return [$mappingId, $mappings];
    }
}
