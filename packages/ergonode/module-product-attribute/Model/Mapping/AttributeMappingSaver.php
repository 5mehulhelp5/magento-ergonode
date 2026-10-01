<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class AttributeMappingSaver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MappingVisibilitySaverInterface $visibilityProvider,
        private readonly AttributeMappingNormalizer $normalizer,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly MappingRowsPersisterInterface $mappingRowsPersister,
        private readonly OptionMappingPersister $optionMappingPersister,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     * @throws LocalizedException
     */
    public function save(array $mappings, array $visibility): array
    {
        $table = $this->resourceConnection->getTableName('ergonode_product_attribute_mapping');
        $existing = $this->loadExisting($table);
        $normalized = $this->normalizer->normalize($mappings, $existing);

        return $this->persist($table, $existing, $normalized, $visibility, true);
    }

    /**
     * Persist mappings without modifying any existing mapping or visibility setting.
     *
     * @param  array<int, array<string, mixed>> $mappings
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     * @throws LocalizedException
     */
    public function saveAdditions(array $mappings): array
    {
        if ($mappings === []) {
            return ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];
        }

        $table = $this->resourceConnection->getTableName('ergonode_product_attribute_mapping');
        $existing = $this->loadExisting($table);
        $additions = $this->normalizer->normalize($mappings, $existing);
        $normalized = $existing;
        $sortOrder = $this->nextSortOrder($existing);
        $changed = false;

        foreach ($additions as $key => $addition) {
            if (isset($normalized[$key])) {
                continue;
            }

            $replacementSortOrder = null;
            foreach ($existing as $existingKey => $row) {
                if (!$this->usesMappingSide($row, $addition)) {
                    continue;
                }

                if (($row['status'] ?? '') !== 'draft') {
                    throw new LocalizedException(
                        __('An attribute selected for automatic mapping is already mapped.')
                    );
                }

                $replacementSortOrder = min(
                    $replacementSortOrder ?? PHP_INT_MAX,
                    (int)($row['sort_order'] ?? 0)
                );
                unset($normalized[$existingKey]);
            }

            $addition['sort_order'] = $replacementSortOrder ?? $sortOrder++;
            $normalized[$key] = $addition;
            $changed = true;
        }

        if (!$changed) {
            return ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];
        }

        return $this->persist($table, $existing, $normalized, null, false);
    }

    /**
     * @param  array<string, array<string, mixed>>   $existing
     * @param  array<string, array<string, mixed>>   $normalized
     * @param  array<int, array<string, mixed>>|null $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     * @throws LocalizedException
     */
    private function persist(
        string $table,
        array $existing,
        array $normalized,
        ?array $visibility,
        bool $deleteStaleArtifacts
    ): array {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            if ($deleteStaleArtifacts) {
                $this->deleteStaleOptionArtifacts($existing, $normalized);
            }
            $stats = $this->mappingRowsPersister->persist($connection, $table, $existing, $normalized);
            if ($visibility !== null) {
                $this->visibilityProvider->saveMany($this->normalizeVisibility($visibility));
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }

            $this->logger->error(
                'Unable to save Ergonode attribute mappings.',
                [
                'exception' => $exception,
                ]
            );
            throw new LocalizedException(__('Unable to save attribute mappings.'));
        }

        return $stats;
    }

    /**
     * @param array<string, array<string, mixed>> $existing
     */
    private function nextSortOrder(array $existing): int
    {
        $sortOrder = -1;

        foreach ($existing as $row) {
            $sortOrder = max($sortOrder, (int)($row['sort_order'] ?? 0));
        }

        return $sortOrder + 1;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $addition
     */
    private function usesMappingSide(array $existing, array $addition): bool
    {
        $existingErgonode = trim((string)($existing['ergonode_attribute_code'] ?? ''));
        $existingMagento = trim((string)($existing['magento_attribute_code'] ?? ''));
        $additionErgonode = trim((string)($addition['ergonode_attribute_code'] ?? ''));
        $additionMagento = trim((string)($addition['magento_attribute_code'] ?? ''));

        return ($existingErgonode !== '' && $existingErgonode === $additionErgonode)
            || ($existingMagento !== '' && $existingMagento === $additionMagento);
    }

    /**
     * @param array<string, array<string, mixed>> $existing
     * @param array<string, array<string, mixed>> $normalized
     */
    private function deleteStaleOptionArtifacts(array $existing, array $normalized): void
    {
        foreach ($existing as $key => $row) {
            if (!isset($normalized[$key])) {
                $this->deleteOptionArtifacts([$row]);
            }
        }

        foreach ($normalized as $key => $mapping) {
            if (isset($existing[$key]) && $this->shouldDeleteOptionMappings($existing[$key], $mapping)) {
                $this->deleteOptionArtifacts([$existing[$key]]);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadExisting(string $table): array
    {
        $rows = $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()
                ->select()
                ->from($table)
        );
        $result = [];

        foreach ($rows as $row) {
            $result[$this->normalizer->key(
                (string)($row['ergonode_attribute_code'] ?? ''),
                (string)($row['magento_attribute_code'] ?? '')
            )] = $row;
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>> $visibility
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
                'entity_type' => 'attribute',
                'source' => $source,
                'identifier' => $identifier,
                'active' => !empty($item['active']),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $attributeMappingRows
     */
    private function deleteOptionArtifacts(array $attributeMappingRows): void
    {
        $mappingIds = [];

        foreach ($attributeMappingRows as $row) {
            $mappingIds[] = (int)($row['mapping_id'] ?? 0);
            $ergonodeCode = trim((string)($row['ergonode_attribute_code'] ?? ''));
            $magentoCode = trim((string)($row['magento_attribute_code'] ?? ''));

            if ($ergonodeCode !== '') {
                $this->visibilityProvider->deleteByParentIdentifier('option', 'ergo', $ergonodeCode);
            }
            if ($magentoCode !== '') {
                $this->visibilityProvider->deleteByParentIdentifier('option', 'magento', $magentoCode);
            }
        }

        $this->optionMappingPersister->deleteByAttributeMappingIds($mappingIds);
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     */
    private function shouldDeleteOptionMappings(array $existing, array $incoming): bool
    {
        if (!$this->isOptionMappingContext($existing)) {
            return false;
        }

        if (!$this->isOptionMappingContext($incoming)) {
            return true;
        }

        foreach (['ergonode_attribute_code', 'magento_attribute_code', 'ergonode_type', 'magento_type'] as $field) {
            if ((string)($existing[$field] ?? '') !== (string)($incoming[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function isOptionMappingContext(array $mapping): bool
    {
        $leftCode = (string)($mapping['ergonode_attribute_code'] ?? '');
        $rightCode = (string)($mapping['magento_attribute_code'] ?? '');
        $leftType = (string)($mapping['ergonode_type'] ?? '');
        $rightType = (string)($mapping['magento_type'] ?? '');

        return $leftCode !== ''
            && $rightCode !== ''
            && $this->typeCompatibility->canMapOptions($leftType, $rightType);
    }
}
