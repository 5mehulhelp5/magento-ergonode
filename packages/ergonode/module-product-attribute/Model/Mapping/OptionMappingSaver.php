<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class OptionMappingSaver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MappingReaderInterface $mappingReader,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly MappingVisibilitySaverInterface $visibilityProvider,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly OptionMappingPersister $optionMappingPersister,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array{
     *     inserted: int,
     *     updated: int,
     *     deleted: int,
     *     unchanged: int,
     *     option_labels_updated: int,
     *     options_created: int,
     *     options_linked: int,
     *     option_sort_order_updated: int,
     *     option_sync_unchanged: int,
     *     option_sync_skipped: int,
     *     option_sync_errors: int
     * }
     * @throws LocalizedException
     */
    public function save(int $attributeMappingId, array $mappings, array $visibility): array
    {
        $attributeMapping = $this->validateAttributeMapping($attributeMappingId);

        $stats = [
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            'option_labels_updated' => 0,
            'options_created' => 0,
            'options_linked' => 0,
            'option_sort_order_updated' => 0,
            'option_sync_unchanged' => 0,
            'option_sync_skipped' => 0,
            'option_sync_errors' => 0,
        ];
        $connection = $this->resourceConnection->getConnection();

        $connection->beginTransaction();
        try {
            $normalized = $this->normalizeMappings($attributeMapping, $mappings);
            $stats = array_replace(
                $stats,
                $this->optionMappingPersister->replace($attributeMappingId, $normalized)
            );
            $this->visibilityProvider->saveMany($this->normalizeVisibility($attributeMapping, $visibility));
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }

            $this->logger->error(
                'Unable to save Ergonode option mappings.',
                [
                'exception' => $exception,
                'attribute_mapping_id' => $attributeMappingId,
                ]
            );
            throw new LocalizedException(__('Unable to save option mappings.'));
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>             $attributeMapping
     * @param  array<int, array<string, mixed>> $mappings
     * @return array<string, array<string, mixed>>
     * @throws LocalizedException
     */
    private function normalizeMappings(array $attributeMapping, array $mappings): array
    {
        $result = [];
        $seenErgonode = [];
        $seenMagento = [];
        $sortOrder = 0;
        $attributeMappingId = (int)$attributeMapping['mapping_id'];

        foreach ($mappings as $mapping) {
            $left = isset($mapping['left']) && is_array($mapping['left']) ? $mapping['left'] : null;
            $right = isset($mapping['right']) && is_array($mapping['right']) ? $mapping['right'] : null;
            $leftCode = $left ? trim((string)($left['code'] ?? '')) : '';
            if (!empty($left['pending_create']) || ($right && $this->isPendingMagentoOption($right))) {
                throw new LocalizedException(__('Options must exist before their mapping can be saved.'));
            }
            $rightCode = $right ? trim((string)($right['code'] ?? '')) : '';
            $rightId = $this->extractMagentoOptionId($rightCode);

            if ($leftCode === '' && $rightId === null) {
                continue;
            }

            if ($leftCode !== '' && isset($seenErgonode[$leftCode])) {
                throw new LocalizedException(__('Ergonode option "%1" is mapped more than once.', $leftCode));
            }
            if ($rightId !== null && isset($seenMagento[$rightId])) {
                throw new LocalizedException(__('Magento option "%1" is mapped more than once.', (string)$rightId));
            }

            if ($leftCode !== '') {
                $seenErgonode[$leftCode] = true;
            }
            if ($rightId !== null) {
                $seenMagento[$rightId] = true;
            }

            $payload = [
                'attribute_mapping_id' => $attributeMappingId,
                'ergonode_option_code' => $leftCode ?: null,
                'magento_option_id' => $rightId,
                'status' => $leftCode !== '' && $rightId !== null ? 'complete' : 'draft',
            ];
            $payload['content_hash'] = $this->optionMappingPersister->hash($payload);
            $payload['sort_order'] = $sortOrder++;
            $result[$this->optionMappingPersister->logicalKey($leftCode, $rightId)] = $payload;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $right
     */
    private function isPendingMagentoOption(array $right): bool
    {
        $code = trim((string)($right['code'] ?? ''));

        return !empty($right['pending_create']) || str_starts_with($code, 'pending_');
    }

    /**
     * @param  array<string, string>            $attributeMapping
     * @param  array<int, array<string, mixed>> $visibility
     * @return array<int, array{
     *     entity_type: string,
     *     source: string,
     *     parent_identifier: string,
     *     identifier: string,
     *     active: bool
     * }>
     */
    private function normalizeVisibility(array $attributeMapping, array $visibility): array
    {
        $result = [];

        foreach ($visibility as $item) {
            $source = (string)($item['source'] ?? '');
            $identifier = trim((string)($item['code'] ?? $item['identifier'] ?? ''));
            $parent = match ($source) {
                'ergo' => $attributeMapping['ergonode_attribute_code'],
                'magento' => $attributeMapping['magento_attribute_code'],
                default => '',
            };

            if ($parent === '' || $identifier === '') {
                continue;
            }

            $result[] = [
                'entity_type' => 'option',
                'source' => $source,
                'parent_identifier' => $parent,
                'identifier' => $identifier,
                'active' => !empty($item['active']),
            ];
        }

        return $result;
    }

    private function extractMagentoOptionId(string $code): ?int
    {
        if ($code === '') {
            return null;
        }

        if (preg_match('/^option_(\d+)$/', $code, $matches)) {
            return (int)$matches[1];
        }

        if (ctype_digit($code)) {
            return (int)$code;
        }

        throw new LocalizedException(__('Invalid Magento option identifier "%1".', $code));
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function validateAttributeMapping(int $attributeMappingId): array
    {
        $attributeMapping = $this->mappingReader->getAttributeRow($attributeMappingId);

        $magentoAttribute = $attributeMapping
            ? $this->magentoAttributeProvider->getAttribute((string)$attributeMapping['magento_attribute_code'])
            : null;
        if ($attributeMapping && $magentoAttribute) {
            $attributeMapping['magento_type'] = (string)$magentoAttribute['type'];
        }

        if (!$attributeMapping || !$magentoAttribute
            || $attributeMapping['ergonode_attribute_code'] === ''
            || $attributeMapping['magento_attribute_code'] === ''
            || !$this->typeCompatibility->canMapOptions(
                $attributeMapping['ergonode_type'],
                $attributeMapping['magento_type']
            )
        ) {
            throw new LocalizedException(__('Options can be mapped only for saved option-mappable attribute pairs.'));
        }

        return $attributeMapping;
    }
}
