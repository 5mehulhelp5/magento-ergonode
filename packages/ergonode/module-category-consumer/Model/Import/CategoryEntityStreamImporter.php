<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Ergonode\Category\Model\Import\CategoryStreamPageReader;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryEntityStreamImporter
{
    public const string PROCESS_CODE = 'category_stream';

    private const string QUERY = <<<'GRAPHQL'
query ErgonodeCategoryStream($first: Int!, $after: String) {
  categoryStream(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { cursor node { code } }
  }
}
GRAPHQL;

    public function __construct(
        private readonly CategoryStreamPageReader $pageReader,
        private readonly CursorStorage $cursorStorage,
        private readonly CategoryDataMappingProvider $categoryMappingQuery,
        private readonly CategoryEntityLoaderInterface $entityLoader,
        private readonly CategoryEntitySynchronizerInterface $entitySynchronizer,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly CategorySynchronizationProgress $progress,
        private readonly CategorySourceAvailability $sourceAvailability
    ) {
    }

    /**
     * @return array{
     *     events: int,
     *     fetched: int,
     *     snapshots: int,
     *     attributes: int,
     *     cursor: string|null
     * }
     */
    public function execute(bool $resetCursor = false): array
    {
        $this->categoryMappingQuery->clear();
        try {
            return $this->import($resetCursor);
        } finally {
            $this->categoryMappingQuery->clear();
        }
    }

    /** @return array<string, mixed> */
    private function import(bool $resetCursor): array
    {
        if ($resetCursor) {
            $this->cursorStorage->reset(self::PROCESS_CODE);
        }
        $missing = $this->sourceAvailability->checkActiveTrees(data: true);
        $this->progress->checkpoint('checking_data');
        if ($this->entitySynchronizer instanceof CategoryDataWorkProviderInterface
            && !$this->entitySynchronizer->hasWork()
        ) {
            if ($missing !== []) {
                throw new LocalizedException(__(implode(' ', $missing)));
            }
            $this->progress->checkpoint('data_skipped');

            return [
                'events' => 0, 'fetched' => 0, 'snapshots' => 0, 'attributes' => 0,
                'cursor' => $this->cursorStorage->get(self::PROCESS_CODE)['cursor'] ?? null,
                'skipped' => true,
            ];
        }
        $this->languageMappingProvider->getLanguageStoreMap();

        $state = $this->cursorStorage->get(self::PROCESS_CODE);
        $cursor = $state['cursor'] ?? null;
        $this->progress->checkpoint('fetching_data_changes');
        $stream = $this->pageReader->readAll(
            self::QUERY,
            'categoryStream',
            $cursor
        );
        $codes = $stream['codes'];
        $cursor = $stream['cursor'];
        $fetched = 0;
        $synchronization = ['snapshots' => 0, 'attributes' => 0];
        foreach (array_chunk($codes, CategoryQueries::ENTITY_BATCH_SIZE) as $chunk) {
            $this->progress->checkpoint('fetching_category_data', $fetched, count($codes));
            $operations = $this->prepareBatch($chunk, $missing);
            $fetched += count(array_unique(array_column(array_column($operations, 'entity'), 'code')));
            $this->progress->checkpoint('updating_category_data', $fetched, count($codes));
            if ($operations !== []) {
                $result = $this->entitySynchronizer->synchronize($operations);
                $synchronization['snapshots'] += $result['snapshots'];
                $synchronization['attributes'] += $result['attributes'];
            }
            unset($operations);
        }
        if ($missing !== []) {
            throw new LocalizedException(__(implode(' ', $missing)));
        }
        $this->progress->checkpoint('saving_data_cursor', $fetched, count($codes));
        if ($cursor !== null && !$this->progress->isManaged()) {
            $this->cursorStorage->save(self::PROCESS_CODE, $cursor);
        }

        return [
            'events' => count($codes),
            'fetched' => $fetched,
            'snapshots' => $synchronization['snapshots'],
            'attributes' => $synchronization['attributes'],
            'cursor' => $cursor,
        ];
    }
    /**
     * @param list<string> $codes
     * @param array<int, string> $missing
     * @return list<array{category_id: int, entity: array<string, mixed>}>
     */
    private function prepareBatch(array $codes, array $missing): array
    {
        $mappings = $this->categoryMappingQuery->getValidMappingsByCodes($codes);
        foreach ($mappings as $code => $targets) {
            $mappings[$code] = array_values(array_filter(
                $targets,
                static fn (array $target): bool => !isset($missing[$target['category_tree_id']])
            ));
            if ($mappings[$code] === []) {
                unset($mappings[$code]);
            }
        }
        if ($mappings === []) {
            return [];
        }
        $entities = $this->entityLoader->loadMany(array_map('strval', array_keys($mappings)));
        $operations = [];
        foreach ($mappings as $code => $targets) {
            $entity = $entities[$code] ?? null;
            if ($entity === null || $entity['code'] !== (string)$code) {
                throw new LocalizedException(__('Mapped Ergonode category "%1" no longer exists.', $code));
            }
            foreach ($targets as $target) {
                $operations[] = ['category_id' => $target['magento_category_id'], 'entity' => $entity];
            }
        }

        return $operations;
    }
}
