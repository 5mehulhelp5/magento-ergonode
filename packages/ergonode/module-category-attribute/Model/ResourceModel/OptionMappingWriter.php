<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\ResourceModel;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\OptionMappingWriterInterface;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class OptionMappingWriter implements OptionMappingWriterInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MappingReaderInterface $mappingReader,
        private readonly MagentoOptionProviderInterface $magentoOptionProvider,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly MappingVisibilitySaverInterface $visibilitySaver,
        private readonly MappingRowsPersisterInterface $mappingRowsPersister,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function save(int $attributeMappingId, array $mappings, array $visibility): array
    {
        $attribute = $this->mappingReader->getAttributeRow($attributeMappingId);
        if (!$attribute || (string)($attribute['status'] ?? '') !== 'complete') {
            throw new LocalizedException(__('Options require a saved category attribute mapping.'));
        }
        if (!$this->typeCompatibility->canMapOptions(
            (string)($attribute['ergonode_type'] ?? ''),
            (string)($attribute['magento_type'] ?? '')
        )) {
            throw new LocalizedException(__('Options require an option-mappable category attribute pair.'));
        }

        $targetOptions = array_column(
            $this->magentoOptionProvider->getOptions((string)$attribute['magento_attribute_code']),
            null,
            'code'
        );
        $normalized = $this->normalizeMappings($attributeMappingId, $mappings, $targetOptions);
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_option_mapping');
        $existing = $this->loadExisting($table, $attributeMappingId);
        $connection->beginTransaction();
        try {
            $stats = $this->mappingRowsPersister->persist($connection, $table, $existing, $normalized);
            $this->visibilitySaver->saveMany($this->normalizeVisibility($attribute, $visibility));
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }
            $this->logger->error('Unable to save Ergonode category option mappings.', ['exception' => $exception]);
            throw new LocalizedException(__('Unable to save category option mappings.'));
        }

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param array<string, array<string, mixed>> $targetOptions
     * @return array<string, array<string, mixed>>
     */
    private function normalizeMappings(int $mappingId, array $mappings, array $targetOptions): array
    {
        $result = [];
        $seenLeft = [];
        $seenRight = [];
        $sortOrder = 0;
        foreach ($mappings as $mapping) {
            $left = is_array($mapping['left'] ?? null) ? $mapping['left'] : null;
            $right = is_array($mapping['right'] ?? null) ? $mapping['right'] : null;
            $leftCode = trim((string)($left['code'] ?? ''));
            $isPending = $right && (
                !empty($right['pending_create'])
                || str_starts_with((string)($right['code'] ?? ''), 'pending_')
            );
            if ($isPending) {
                throw new LocalizedException(__('Resolve Magento category options before saving their mappings.'));
            }
            $rightId = $this->optionId(trim((string)($right['code'] ?? '')));
            $rightCode = $rightId !== null ? 'option_' . $rightId : '';
            if ($rightCode !== '' && !isset($targetOptions[$rightCode])) {
                throw new LocalizedException(__(
                    'Magento category option "%1" is not available for the mapped attribute.',
                    $rightCode
                ));
            }
            if ($leftCode === '' && $rightId === null) {
                continue;
            }
            if ($leftCode !== '' && isset($seenLeft[$leftCode])) {
                throw new LocalizedException(__('Ergonode category option "%1" is mapped more than once.', $leftCode));
            }
            if ($rightId !== null && isset($seenRight[$rightId])) {
                throw new LocalizedException(__('Magento category option "%1" is mapped more than once.', $rightId));
            }
            if ($leftCode !== '') {
                $seenLeft[$leftCode] = true;
            }
            if ($rightId !== null) {
                $seenRight[$rightId] = true;
            }
            $payload = [
                'attribute_mapping_id' => $mappingId,
                'ergonode_option_code' => $leftCode ?: null,
                'magento_option_id' => $rightId,
                'status' => $leftCode !== '' && $rightId !== null ? 'complete' : 'draft',
            ];
            $payload['content_hash'] = $this->mappingRowsPersister->hash($payload);
            $payload['sort_order'] = $sortOrder++;
            $result[$this->logicalKey($leftCode, $rightId)] = $payload;
        }

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    private function loadExisting(string $table, int $mappingId): array
    {
        $result = [];
        foreach ($this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from($table)
                ->where('attribute_mapping_id = ?', $mappingId)
        ) as $row) {
            $result[$this->logicalKey(
                trim((string)($row['ergonode_option_code'] ?? '')),
                $row['magento_option_id'] !== null ? (int)$row['magento_option_id'] : null
            )] = $row;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $attribute
     * @param array<int, array<string, mixed>> $visibility
     * @return array<int, array{
     *     entity_type: string,
     *     source: string,
     *     parent_identifier: string,
     *     identifier: string,
     *     active: bool
     * }>
     */
    private function normalizeVisibility(array $attribute, array $visibility): array
    {
        $result = [];
        foreach ($visibility as $item) {
            $source = (string)($item['source'] ?? '');
            $identifier = trim((string)($item['code'] ?? $item['identifier'] ?? ''));
            $parent = $source === 'ergo'
                ? (string)$attribute['ergonode_attribute_code']
                : (string)$attribute['magento_attribute_code'];
            if (!in_array($source, ['ergo', 'magento'], true) || $identifier === '') {
                continue;
            }
            $result[] = [
                'entity_type' => 'category_option',
                'source' => $source,
                'parent_identifier' => $parent,
                'identifier' => $identifier,
                'active' => !empty($item['active']),
            ];
        }

        return $result;
    }

    private function optionId(string $code): ?int
    {
        if ($code === '') {
            return null;
        }
        if (!preg_match('/^(?:option_)?(\d+)$/', $code, $matches)) {
            throw new LocalizedException(__('Invalid Magento category option identifier "%1".', $code));
        }

        return (int)$matches[1];
    }

    private function logicalKey(string $leftCode, ?int $rightId): string
    {
        if ($leftCode !== '' && $rightId !== null) {
            return 'full:' . $leftCode . '|' . $rightId;
        }

        return $leftCode !== '' ? 'ergo:' . $leftCode : 'magento:' . (string)$rightId;
    }
}
