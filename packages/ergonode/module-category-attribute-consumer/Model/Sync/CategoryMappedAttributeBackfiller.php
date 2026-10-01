<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Snapshot\CategoryBackfillSnapshotReaderInterface;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Throwable;

class CategoryMappedAttributeBackfiller
{
    public function __construct(
        private readonly CategoryAttributeSourcePreparation $attributePreparation,
        private readonly CategoryBackfillSnapshotReaderInterface $snapshotReader,
        private readonly Json $json,
        private readonly CategoryAttributeWriter $attributeWriter,
        private readonly LoggerInterface $logger,
        private readonly CategoryAttributeConfigProvider $configProvider,
        private readonly CategoryDataMappingProvider $mappingProvider,
        private readonly CategoryCacheInvalidator $cacheInvalidator
    ) {
    }

    /** @return array{categories: int, values: int, errors: int} */
    public function execute(): array
    {
        if (!$this->configProvider->isAttributeSynchronizationEnabled()) {
            return ['categories' => 0, 'values' => 0, 'errors' => 0];
        }
        $this->attributePreparation->prepare();
        $rows = $this->snapshotReader->getRows();
        $this->mappingProvider->clear();
        $eligible = [];
        $mappingsByCode = $this->mappingProvider->getValidMappingsByCodes(array_column($rows, 'category_code'));
        foreach ($mappingsByCode as $code => $mappings) {
            foreach ($mappings as $mapping) {
                $eligible[$mapping['category_tree_id'] . '|' . $code . '|' . $mapping['magento_category_id']] = true;
            }
        }
        $stats = ['categories' => 0, 'values' => 0, 'errors' => 0];
        $processed = [];
        $changed = [];
        foreach ($rows as $row) {
            $categoryId = (int)$row['magento_category_id'];
            $key = (string)$row['category_code'] . '|' . $categoryId;
            if ($categoryId <= 0 || isset($processed[$key])
                || !isset($eligible[(int)$row['category_tree_id'] . '|' . $key])
            ) {
                continue;
            }
            $processed[$key] = true;
            $writing = false;
            try {
                $attributes = $this->json->unserialize((string)$row['attributes_json']);
                if (!is_array($attributes)) {
                    throw new InvalidArgumentException('Category attribute snapshot is not an array.');
                }
                // A writer failure may follow an earlier EAV write for the same category.
                $writing = true;
                $written = $this->attributeWriter->writeMappedValues($categoryId, $attributes);
                if ($written > 0) {
                    $changed[$categoryId] = $categoryId;
                }
                $stats['values'] += $written;
                $stats['categories']++;
            } catch (Throwable $exception) {
                if ($writing) {
                    $changed[$categoryId] = $categoryId;
                }
                $stats['errors']++;
                $this->logger->error('Unable to backfill mapped category attributes.', [
                    'category_code' => (string)$row['category_code'],
                    'magento_category_id' => $categoryId,
                    'exception' => $exception,
                ]);
            }
        }

        if ($changed !== []) {
            $this->cacheInvalidator->invalidateCategories(array_values($changed));
        }
        return $stats;
    }
}
