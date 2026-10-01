<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\ResourceModel;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttribute\Api\MappingPolicyInterface;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class AttributeMappingWriter implements AttributeMappingWriterInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MappingVisibilitySaverInterface $visibilitySaver,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly MappingPolicyInterface $mappingPolicy,
        private readonly MappingRowsPersisterInterface $mappingRowsPersister,
        private readonly LoggerInterface $logger
    ) {
    }

    public function validate(array $mappings): void
    {
        $this->normalizeMappings($mappings);
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function save(array $mappings, array $visibility): array
    {
        $normalized = $this->normalizeMappings($mappings);
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_attribute_mapping');
        $existing = $this->loadExisting($table);
        $connection->beginTransaction();
        try {
            $stats = $this->mappingRowsPersister->persist($connection, $table, $existing, $normalized);
            $this->visibilitySaver->saveMany($this->normalizeVisibility($visibility));
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }
            $this->logger->error('Unable to save Ergonode category attribute mappings.', ['exception' => $exception]);
            throw new LocalizedException(__('Unable to save category attribute mappings.'));
        }

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return array<string, array<string, mixed>>
     */
    private function normalizeMappings(array $mappings): array
    {
        $result = [];
        $seenErgonode = [];
        $seenMagento = [];
        $sortOrder = 0;

        foreach ($mappings as $mapping) {
            $left = is_array($mapping['left'] ?? null) ? $mapping['left'] : null;
            $right = is_array($mapping['right'] ?? null) ? $mapping['right'] : null;
            $leftCode = trim((string)($left['code'] ?? ''));
            $rightCode = trim((string)($right['code'] ?? ''));
            if ($leftCode === '' && $rightCode === '') {
                continue;
            }

            if ($rightCode !== '' && !$this->mappingPolicy->isMappable($rightCode)) {
                throw new LocalizedException(__('Magento category attribute "%1" is not available.', $rightCode));
            }
            if (!empty($right['pending_create'])) {
                throw new LocalizedException(__('Resolve Magento category attributes before saving their mappings.'));
            }

            if ($leftCode !== '' && isset($seenErgonode[$leftCode])) {
                throw new LocalizedException(
                    __('Ergonode category attribute "%1" is mapped more than once.', $leftCode)
                );
            }
            if ($rightCode !== '' && isset($seenMagento[$rightCode])) {
                throw new LocalizedException(
                    __('Magento category attribute "%1" is mapped more than once.', $rightCode)
                );
            }

            $leftType = strtolower(trim((string)($left['type'] ?? '')));
            $rightType = strtolower(trim((string)($right['type'] ?? '')));
            $complete = $leftCode !== '' && $rightCode !== '';
            if ($complete && !$this->typeCompatibility->canMapAttributes($leftType, $rightType)) {
                throw new LocalizedException(__(
                    'Category attribute "%1" cannot be mapped to "%2" because types do not match.',
                    $leftCode,
                    $rightCode
                ));
            }

            if ($leftCode !== '') {
                $seenErgonode[$leftCode] = true;
            }
            if ($rightCode !== '') {
                $seenMagento[$rightCode] = true;
            }
            $payload = [
                'ergonode_attribute_code' => $leftCode ?: null,
                'magento_attribute_code' => $rightCode ?: null,
                'ergonode_type' => $leftType ?: null,
                'magento_type' => $rightType ?: null,
                'status' => $complete ? 'complete' : 'draft',
            ];
            $payload['content_hash'] = $this->mappingRowsPersister->hash($payload);
            $payload['sort_order'] = $sortOrder++;
            $result[$this->logicalKey($leftCode, $rightCode)] = $payload;
        }

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    private function loadExisting(string $table): array
    {
        $result = [];
        foreach ($this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()->from($table)
        ) as $row) {
            $result[$this->logicalKey(
                trim((string)($row['ergonode_attribute_code'] ?? '')),
                trim((string)($row['magento_attribute_code'] ?? ''))
            )] = $row;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $visibility
     * @return array<int, array{entity_type: string, source: string, identifier: string, active: bool}>
     */
    private function normalizeVisibility(array $visibility): array
    {
        $result = [];
        foreach ($visibility as $item) {
            $source = (string)($item['source'] ?? '');
            $identifier = trim((string)($item['code'] ?? $item['identifier'] ?? ''));
            if (!in_array($source, ['ergo', 'magento'], true) || $identifier === '') {
                continue;
            }
            $result[] = [
                'entity_type' => 'category_attribute',
                'source' => $source,
                'identifier' => $identifier,
                'active' => !empty($item['active']),
            ];
        }

        return $result;
    }

    private function logicalKey(string $leftCode, string $rightCode): string
    {
        if ($leftCode !== '' && $rightCode !== '') {
            return 'full:' . $leftCode . '|' . $rightCode;
        }

        return $leftCode !== '' ? 'ergo:' . $leftCode : 'magento:' . $rightCode;
    }
}
