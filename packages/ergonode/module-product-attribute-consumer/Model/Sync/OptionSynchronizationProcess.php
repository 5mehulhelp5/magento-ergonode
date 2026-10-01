<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Sync;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttributeConsumer\Api\OptionSynchronizationProcessInterface;
use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttributeConsumer\Model\Import\OptionSnapshotReconciler;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingProvider;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class OptionSynchronizationProcess implements OptionSynchronizationProcessInterface
{
    private const string LOCK_NAME = 'ergonode_option_synchronization';

    public function __construct(
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly AttributeCacheRefresherInterface $attributeCacheRefresher,
        private readonly MagentoOptionSyncer $magentoOptionSyncer,
        private readonly ProductAttributeConfigProvider $attributeConfigProvider,
        private readonly OptionSnapshotReconciler $snapshotReconciler,
        private readonly ChangeReport $changeReport,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    public function execute(?int $attributeMappingId = null): array
    {
        $this->changeReport->reset();

        return $this->executeWithLock($attributeMappingId, null);
    }

    public function executeForAttributeCodes(array $attributeCodes): array
    {
        $attributeCodes = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $code): string => trim((string)$code),
                $attributeCodes
            ),
            static fn (string $code): bool => $code !== ''
        )));
        if ($attributeCodes === []) {
            return ['mappings' => [], 'summary' => $this->emptyStats()];
        }

        return $this->executeWithLock(null, $attributeCodes);
    }

    /** @param string[]|null $attributeCodes */
    private function executeWithLock(?int $attributeMappingId, ?array $attributeCodes): array
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Option synchronization is already running.'));
        }

        try {
            return $this->executeLocked($attributeMappingId, $attributeCodes);
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * @param string[]|null $attributeCodes
     * @return array{
     *     mappings: array<int, array{
     *         mapping_id: int,
     *         ergonode_attribute_code: string,
     *         magento_attribute_code: string,
     *         stats: array<string, int>
     *     }>,
     *     summary: array<string, int>
     * }
     */
    private function executeLocked(?int $attributeMappingId, ?array $attributeCodes): array
    {
        $mappings = $attributeMappingId !== null
            ? [$this->requireMapping($attributeMappingId)]
            : $this->loadAllMappings($attributeCodes);
        $result = [];
        $summary = $this->emptyStats();

        foreach ($mappings as $mapping) {
            $this->attributeCacheRefresher->refreshOptions((string)$mapping['ergonode_attribute_code']);
            if ($this->attributeConfigProvider->shouldDeleteMissingMagentoOptions()
                && ($mapping['magento_has_custom_source'] ?? true) === false
            ) {
                $this->snapshotReconciler->reconcile(
                    (int)$mapping['mapping_id'],
                    (string)$mapping['ergonode_attribute_code'],
                    (string)$mapping['magento_attribute_code']
                );
            }
            $stats = $this->magentoOptionSyncer->syncAttributeMapping($mapping);
            $this->mergeStats($summary, $stats);
            $result[] = [
                'mapping_id' => (int)$mapping['mapping_id'],
                'ergonode_attribute_code' => (string)$mapping['ergonode_attribute_code'],
                'magento_attribute_code' => (string)$mapping['magento_attribute_code'],
                'stats' => $stats,
            ];
        }

        return ['mappings' => $result, 'summary' => $summary];
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function requireMapping(int $attributeMappingId): array
    {
        $mapping = $this->attributeMappingProvider->getMappingRow($attributeMappingId);
        if ($mapping === null) {
            throw new LocalizedException(__('Attribute mapping "%1" does not exist.', $attributeMappingId));
        }
        $this->assertOptionMapping($mapping);

        return $mapping;
    }

    /**
     * @param string[]|null $attributeCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadAllMappings(?array $attributeCodes): array
    {
        $mappings = [];
        $attributeCodeMap = $attributeCodes !== null ? array_fill_keys($attributeCodes, true) : null;

        foreach ($this->attributeMappingProvider->getOptionAttributeContexts() as $context) {
            $contextAttributeCode = trim((string)($context['left']['code'] ?? ''));
            if ($attributeCodeMap !== null
                && !isset($attributeCodeMap[$contextAttributeCode])
            ) {
                continue;
            }
            $mapping = $this->attributeMappingProvider->getMappingRow((int)$context['mapping_id']);
            if ($mapping === null) {
                continue;
            }
            $this->assertOptionMapping($mapping);
            $mappings[] = $mapping;
        }

        return $mappings;
    }

    /**
     * @param array<string, mixed> $mapping
     * @throws LocalizedException
     */
    private function assertOptionMapping(array $mapping): void
    {
        if ((string)($mapping['ergonode_attribute_code'] ?? '') === ''
            || (string)($mapping['magento_attribute_code'] ?? '') === ''
            || !$this->typeCompatibility->canMapOptions(
                (string)($mapping['ergonode_type'] ?? ''),
                (string)($mapping['magento_type'] ?? '')
            )
        ) {
            throw new LocalizedException(
                __('Options can be synchronized only for saved option-mappable attribute pairs.')
            );
        }
    }

    /** @return array<string, int> */
    private function emptyStats(): array
    {
        return [
            'created' => 0,
            'linked' => 0,
            'mappings_inserted' => 0,
            'mappings_updated' => 0,
            'labels_updated' => 0,
            'sort_order_updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }

    /**
     * @param array<string, int> $summary
     * @param array<string, int> $stats
     */
    private function mergeStats(array &$summary, array $stats): void
    {
        foreach ($summary as $name => $value) {
            $summary[$name] = $value + (int)($stats[$name] ?? 0);
        }
    }
}
