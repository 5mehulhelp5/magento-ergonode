<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Import;

use Ergonode\Core\Api\PersistedBatchImportRunnerInterface;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationBatchInterface;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationProcessInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeImportProcess implements AttributeSynchronizationProcessInterface
{
    public const string PROCESS_CODE = 'attributeStream';
    private const int DEFAULT_PAGE_SIZE = 200;

    public function __construct(
        private readonly PersistedBatchImportRunnerInterface $importRunner,
        private readonly AttributeSynchronizationBatchInterface $synchronizationBatch,
        private readonly ChangeReport $changeReport
    ) {
    }

    /**
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     created_attributes: int,
     *     auto_mapped: int,
     *     mapping_conflicts: int,
     *     option_mappings: int,
     *     options: array<string, int>,
     *     review_required: int
     * }
     * @throws LocalizedException
     */
    public function executeBatch(?int $pageSize = null): array
    {
        $this->changeReport->reset();

        $mappingStats = $this->emptyMappingStats();
        $optionResult = $this->emptyOptionResult();
        $reviewRequired = 0;
        $result = $this->importRunner->executeBatch(
            self::PROCESS_CODE,
            self::DEFAULT_PAGE_SIZE,
            $pageSize,
            $this->synchronizedBatchImporter($mappingStats, $optionResult, $reviewRequired)
        );

        return $result + $this->synchronizationStats($mappingStats, $optionResult, $reviewRequired);
    }

    /**
     * @return array{
     *     batches: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     has_more: bool,
     *     created_attributes: int,
     *     auto_mapped: int,
     *     mapping_conflicts: int,
     *     option_mappings: int,
     *     options: array<string, int>,
     *     review_required: int
     * }
     * @throws LocalizedException
     */
    public function executeUntilComplete(?int $pageSize = null, int $maxBatches = 100): array
    {
        $this->changeReport->reset();

        $mappingStats = $this->emptyMappingStats();
        $optionResult = $this->emptyOptionResult();
        $reviewRequired = 0;
        $result = $this->importRunner->executeUntilComplete(
            self::PROCESS_CODE,
            self::DEFAULT_PAGE_SIZE,
            $pageSize,
            $maxBatches,
            $this->synchronizedBatchImporter($mappingStats, $optionResult, $reviewRequired)
        );

        return $result + $this->synchronizationStats($mappingStats, $optionResult, $reviewRequired);
    }

    public function reset(): void
    {
        $this->importRunner->reset(self::PROCESS_CODE);
    }

    /**
     * @param  array<string, int>                                $mappingStats
     * @param  array{mappings: int, summary: array<string, int>} $optionResult
     * @return callable(?string, int): array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     attribute_codes: string[]
     * }
     */
    private function synchronizedBatchImporter(
        array &$mappingStats,
        array &$optionResult,
        int &$reviewRequired
    ): callable {
        $refreshDefinitions = true;

        return function (
            ?string $cursor,
            int $pageSize
        ) use (
            &$mappingStats,
            &$optionResult,
            &$reviewRequired,
            &$refreshDefinitions
        ): array {
            $batch = $this->synchronizationBatch->executeAutomatic($cursor, $pageSize, $refreshDefinitions);
            if (!$batch['completion']['cursor_advance_allowed']) {
                throw new LocalizedException(__('Attribute synchronization did not complete; cursor was preserved.'));
            }
            $refreshDefinitions = false;
            $this->mergeStats($mappingStats, $batch['mapping']);
            $optionResult['mappings'] += count($batch['options']['mappings']);
            $this->mergeStats($optionResult['summary'], $batch['options']['summary']);
            $reviewRequired += $batch['completion']['review_required'];

            return $batch['import'];
        };
    }

    /**
     * @return array<string, int>
     */
    private function emptyMappingStats(): array
    {
        return [
            'matched' => 0,
            'conflicts' => 0,
            'created' => 0,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
        ];
    }

    /**
     * @return array{mappings: int, summary: array<string, int>}
     */
    private function emptyOptionResult(): array
    {
        return [
            'mappings' => 0,
            'summary' => [
                'created' => 0,
                'linked' => 0,
                'mappings_inserted' => 0,
                'mappings_updated' => 0,
                'labels_updated' => 0,
                'sort_order_updated' => 0,
                'unchanged' => 0,
                'skipped' => 0,
                'errors' => 0,
            ],
        ];
    }

    /**
     * @param array<string, int> $target
     * @param array<string, int> $source
     */
    private function mergeStats(array &$target, array $source): void
    {
        foreach ($target as $name => $value) {
            $target[$name] = $value + (int)($source[$name] ?? 0);
        }
    }

    /**
     * @param  array<string, int>                                $mappingStats
     * @param  array{mappings: int, summary: array<string, int>} $optionResult
     * @return array{
     *     created_attributes: int,
     *     auto_mapped: int,
     *     mapping_conflicts: int,
     *     option_mappings: int,
     *     options: array<string, int>,
     *     review_required: int
     * }
     */
    private function synchronizationStats(
        array $mappingStats,
        array $optionResult,
        int $reviewRequired
    ): array {
        return [
            'created_attributes' => $mappingStats['created'],
            'auto_mapped' => $mappingStats['inserted'],
            'mapping_conflicts' => $mappingStats['conflicts'],
            'option_mappings' => $optionResult['mappings'],
            'options' => $optionResult['summary'],
            'review_required' => $reviewRequired,
        ];
    }
}
