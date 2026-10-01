<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingSaver as MappingSaver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class OptionMappingSaver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly OptionMappingMagentoSyncer $optionMappingMagentoSyncer,
        private readonly MappingSaver $mappingSaver
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array<string, int>
     */
    public function save(int $attributeMappingId, array $mappings, array $visibility): array
    {
        $context = $this->attributeMappingProvider->getMappingRow($attributeMappingId);
        if (!$context || $context['ergonode_attribute_code'] === '' || $context['magento_attribute_code'] === ''
            || !$this->typeCompatibility->canMapOptions($context['ergonode_type'], $context['magento_type'])
        ) {
            throw new LocalizedException(__('Options can be mapped only for saved option-mappable attribute pairs.'));
        }
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $created = 0;
            foreach ($mappings as &$mapping) {
                $right = $mapping['right'] ?? null;
                if (!is_array($right)
                    || (empty($right['pending_create']) && !str_starts_with((string)($right['code'] ?? ''), 'pending_'))
                ) {
                    continue;
                }
                if (!empty($context['magento_has_custom_source'])) {
                    throw new LocalizedException(
                        __('New options cannot be created for this native Magento attribute.')
                    );
                }
                $mapping['right'] = $this->optionMappingMagentoSyncer->createPendingMagentoOption(
                    $context,
                    $mapping['left'] ?? null,
                    $right
                );
                $created += !empty($mapping['right']['created']) ? 1 : 0;
            }
            unset($mapping);
            $stats = $this->mappingSaver->save($attributeMappingId, $mappings, $visibility);
            $stats['options_created'] = $created;

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $stats;
    }
}
