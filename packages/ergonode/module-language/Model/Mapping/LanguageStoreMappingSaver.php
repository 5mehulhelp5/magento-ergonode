<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Mapping;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Exception\MappingConflictException;
use Ergonode\Language\Model\Data\MappingStateDto;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Ergonode\Language\Api\LanguageStoreMappingSaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class LanguageStoreMappingSaver implements LanguageStoreMappingSaverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LanguageMappingStateProviderInterface $stateProvider,
        private readonly MappingVisibilitySaverInterface $visibilitySaver,
        private readonly LoggerInterface $logger,
        private readonly MappingLock $lock,
        private readonly MappingCache $cache
    ) {
    }

    public function save(array $mappings, array $visibility, string $expectedRevision): array
    {
        return $this->lock->run(function () use ($mappings, $visibility, $expectedRevision): array {
            $state = $this->stateProvider->getState();
            if ($expectedRevision === '' || !hash_equals($state->revision, $expectedRevision)) {
                throw new MappingConflictException();
            }
            $stats = $this->persist(
                $this->normalizeMappings($mappings, $state),
                $this->normalizeVisibility($visibility),
                $state
            );
            $this->cache->invalidate();
            return ['stats' => $stats, 'revision' => $this->stateProvider->getState()->revision];
        });
    }

    /**
     * @param array<string, array{
     *     store_id: int|null, language_code: string|null, sort_order: int, is_manual: int
     * }> $normalized
     * @param array<int, array{entity_type: string, source: string, identifier: string, active: bool}> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    private function persist(array $normalized, array $visibility, MappingStateDto $state): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_language_store_mapping');
        $existing = $this->loadExisting($state);

        $connection->beginTransaction();
        try {
            foreach ($existing as $key => $mapping) {
                if (!isset($normalized[$key])) {
                    $connection->delete($table, ['mapping_id = ?' => $mapping['mapping_id']]);
                    $stats['deleted']++;
                    continue;
                }

                if ($normalized[$key]['language_code'] === $mapping['language_code']
                    && $normalized[$key]['store_id'] === $mapping['store_id']
                    && $normalized[$key]['is_manual'] === $mapping['is_manual']
                    && $normalized[$key]['sort_order'] === $mapping['sort_order']
                ) {
                    $stats['unchanged']++;
                    continue;
                }

                $connection->update($table, $normalized[$key], ['mapping_id = ?' => $mapping['mapping_id']]);
                $stats['updated']++;
            }

            foreach ($normalized as $key => $mapping) {
                if (isset($existing[$key])) {
                    continue;
                }

                $connection->insert($table, $mapping);
                $stats['inserted']++;
            }

            $this->visibilitySaver->saveMany($visibility);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            $this->logger->error('Unable to save Ergonode language store-view mappings.', [
                'exception' => $exception,
            ]);

            throw new LocalizedException(__('Unable to save language mappings.'));
        }

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return array<string, array{store_id: int|null, language_code: string|null, sort_order: int, is_manual: int}>
     * @throws LocalizedException
     */
    private function normalizeMappings(array $mappings, MappingStateDto $state): array
    {
        $availableStores = $state->stores;
        $availableLanguages = array_fill_keys($state->codes, true);
        foreach ($state->rows as $row) {
            if ($row['language_code'] !== null) {
                $availableLanguages[$row['language_code']] = true;
            }
        }
        $result = [];
        $seenStores = [];
        $sortOrder = 0;

        foreach ($mappings as $mapping) {
            [$languageCode, $rawStoreId] = $this->mappingValues($mapping);
            $storeId = null;
            if ($rawStoreId !== null && $rawStoreId !== '') {
                $validatedStoreId = filter_var(
                    $rawStoreId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 0]]
                );
                if ($validatedStoreId === false) {
                    throw new LocalizedException(__('The Magento store scope identifier is invalid.'));
                }
                $storeId = $validatedStoreId;
            }

            if ($storeId !== null && !isset($availableStores[$storeId])) {
                throw new LocalizedException(__('Store View ID %1 is not available for mapping.', $storeId));
            }
            if ($languageCode !== '' && !isset($availableLanguages[$languageCode])) {
                throw new LocalizedException(__('Ergonode language "%1" is not available for mapping.', $languageCode));
            }
            if ($storeId !== null && isset($seenStores[$storeId])) {
                throw new LocalizedException(__('A Store View can be mapped to only one Ergonode language.'));
            }
            if ($storeId !== null) {
                $seenStores[$storeId] = true;
            }

            $result[$this->logicalKey($languageCode, $storeId)] = [
                'store_id' => $storeId,
                'language_code' => $languageCode !== '' ? $languageCode : null,
                'sort_order' => $sortOrder++,
                'is_manual' => 1,
            ];
        }

        return $result;
    }

    /** @return array{string, int|string|null} */
    private function mappingValues(mixed $mapping): array
    {
        if (!is_array($mapping)) {
            throw new LocalizedException(__('Invalid language mapping row.'));
        }
        foreach (['left', 'right'] as $side) {
            if (isset($mapping[$side]) && (!is_array($mapping[$side])
                || !array_key_exists('code', $mapping[$side]))) {
                throw new LocalizedException(__('Invalid language mapping row.'));
            }
        }
        $languageCode = $mapping['left']['code'] ?? $mapping['language_code'] ?? null;
        $storeId = $mapping['right']['code'] ?? $mapping['store_id'] ?? null;
        if (($languageCode !== null && !is_string($languageCode))
            || ($storeId !== null && !is_int($storeId) && !is_string($storeId))) {
            throw new LocalizedException(__('Invalid language mapping row.'));
        }
        $languageCode = trim($languageCode ?? '');
        if ($languageCode === '' && ($storeId === null || $storeId === '')) {
            throw new LocalizedException(__('Invalid language mapping row.'));
        }

        return [$languageCode, $storeId];
    }

    /**
     * @return array<string, array{
     *     mapping_id: int,
     *     store_id: int|null,
     *     language_code: string|null,
     *     sort_order: int,
     *     is_manual: int
     * }>
     */
    private function loadExisting(MappingStateDto $state): array
    {
        $result = [];

        foreach ($state->rows as $row) {
            $storeId = $row['store_id'];
            $languageCode = $row['language_code'] ?? '';
            $result[$this->logicalKey($languageCode, $storeId)] = [
                'mapping_id' => $row['mapping_id'],
                'store_id' => $storeId,
                'language_code' => $row['language_code'],
                'sort_order' => $row['sort_order'],
                'is_manual' => $row['is_manual'],
            ];
        }

        return $result;
    }

    private function logicalKey(string $languageCode, ?int $storeId): string
    {
        if ($languageCode !== '' && $storeId !== null) {
            return 'full:' . $languageCode . '|' . $storeId;
        }

        return $languageCode !== '' ? 'ergo:' . $languageCode : 'magento:' . (string)$storeId;
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
            if ($source === 'magento' && $identifier === '0' && empty($item['active'])) {
                throw new LocalizedException(__('Magento Default Values cannot be excluded from language mapping.'));
            }

            $result[] = [
                'entity_type' => 'language',
                'source' => $source,
                'identifier' => $identifier,
                'active' => !empty($item['active']),
            ];
        }

        return $result;
    }
}
