<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Snapshot\CategoryEntitySnapshotWriter;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;

use Ergonode\CategoryConsumer\Api\CategoryNameSynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Ergonode\Core\Model\Report\ChangeReport;
use Throwable;

class CategoryEntitySynchronizer implements CategoryEntitySynchronizerInterface, CategoryDataWorkProviderInterface
{
    public function __construct(
        private readonly CategoryEntitySnapshotWriter $snapshotWriter,
        private readonly CategoryAttributeWriter $attributeWriter,
        private readonly CategoryCacheInvalidator $cacheInvalidator,
        private readonly ChangeReport $changeReport,
        private readonly CategoryNameSynchronizerInterface $nameSynchronizer,
        private readonly CategoryDataWorkProvider $workProvider
    ) {
    }

    public function hasWork(): bool
    {
        return $this->workProvider->hasWork();
    }

    /**
     * @param array<int, array{
     *     category_id: int,
     *     entity: array{
     *         code: string,
     *         labels: array<string, string>,
     *         attributes: array<int, array<string, mixed>>,
     *         hash: string,
     *         raw: array<string, mixed>
     *     }
     * }> $operations
     * @return array{
     *     snapshots: int,
     *     snapshot_statuses: array<string, 'inserted'|'updated'|'unchanged'>,
     *     attributes: int,
     *     changed_category_ids: int[]
     * }
     */
    public function synchronize(array $operations): array
    {
        $stats = [
            'snapshots' => 0,
            'snapshot_statuses' => [],
            'attributes' => 0,
            'changed_category_ids' => [],
        ];
        $processedSnapshots = [];
        $processedCategories = [];
        $attempted = [];
        try {
            foreach ($operations as $operation) {
                $categoryId = (int)($operation['category_id'] ?? 0);
                $attempted[$categoryId] = $categoryId;
                $entity = $operation['entity'] ?? null;
                if ($categoryId <= 0 || !is_array($entity)) {
                    continue;
                }
                $code = trim((string)($entity['code'] ?? ''));
                $operationKey = $code . '|' . $categoryId;
                if ($code === '' || isset($processedCategories[$operationKey])) {
                    continue;
                }
                $processedCategories[$operationKey] = true;
                if (!isset($processedSnapshots[$code])) {
                    $snapshotStatus = $this->snapshotWriter->save($entity);
                    $processedSnapshots[$code] = true;
                    $stats['snapshot_statuses'][$code] = $snapshotStatus;
                    if ($snapshotStatus !== 'unchanged') {
                        $stats['snapshots']++;
                        $this->changeReport->add(
                            'category_snapshot',
                            $code,
                            $snapshotStatus,
                            $snapshotStatus === ChangeReport::ACTION_INSERTED
                                ? 'Stored Ergonode category snapshot.'
                                : 'Updated Ergonode category snapshot.'
                        );
                    }
                }
                $names = $this->nameSynchronizer->synchronize($categoryId, (array)($entity['labels'] ?? []));
                $attributes = $names + $this->attributeWriter->writeMappedValues(
                    $categoryId,
                    (array)($entity['attributes'] ?? [])
                );
                $stats['attributes'] += $attributes;
                if ($attributes > 0) {
                    $stats['changed_category_ids'][] = $categoryId;
                    $this->changeReport->add(
                        'category_attributes',
                        $code,
                        ChangeReport::ACTION_UPDATED,
                        'Updated mapped Magento category values.',
                        ['category_id' => $categoryId, 'values_changed' => $attributes]
                    );
                }
            }
        } catch (Throwable $exception) {
            $this->cacheInvalidator->invalidateCategories(array_values($attempted));
            throw $exception;
        }
        $stats['changed_category_ids'] = array_values(array_unique($stats['changed_category_ids']));
        if ($stats['changed_category_ids'] !== []) {
            $this->cacheInvalidator->invalidateCategories($stats['changed_category_ids']);
        }

        return $stats;
    }
}
