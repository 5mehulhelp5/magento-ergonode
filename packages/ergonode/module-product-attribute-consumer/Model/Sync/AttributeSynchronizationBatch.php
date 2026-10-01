<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Sync;

use Ergonode\AttributeConsumer\Api\AttributeBatchImporterInterface;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Api\OptionSynchronizationInterface;
use Ergonode\ProductAttributeConsumer\Api\AttributeAutoMapperInterface;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationBatchInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class AttributeSynchronizationBatch implements AttributeSynchronizationBatchInterface
{
    private const string LOCK_NAME = 'ergonode_attribute_synchronization_batch';

    public function __construct(
        private readonly AttributeDefinitionSynchronizationInterface $definitionSynchronization,
        private readonly AttributeBatchImporterInterface $attributeBatchImporter,
        private readonly AttributeAutoMapperInterface $attributeAutoMapper,
        private readonly OptionSynchronizationInterface $optionSynchronizationPool,
        private readonly AttributeSynchronizationOutcomePolicy $outcomePolicy,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    public function executeAutomatic(
        ?string $cursor = null,
        ?int $pageSize = null,
        bool $refreshDefinitions = true
    ): array {
        if ($refreshDefinitions) {
            $this->definitionSynchronization->synchronize();
        }

        return $this->executeWithLock($cursor, $pageSize);
    }

    /**
     * @return array{
     *     import: array{
     *         has_more: bool,
     *         cursor: string|null,
     *         page_size: int,
     *         imported: int,
     *         changed: int,
     *         unchanged: int,
     *         attribute_codes: string[]
     *     },
     *     mapping: array<string, int>,
     *     options: array{mappings: array<int, array<string, mixed>>, summary: array<string, int>},
     *     completion: array{cursor_advance_allowed: bool, review_required: int}
     * }
     */
    private function executeWithLock(?string $cursor, ?int $pageSize): array
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Attribute synchronization is already running.'));
        }

        try {
            $import = $this->attributeBatchImporter->import($cursor, $pageSize);
            $mapping = $this->attributeAutoMapper->synchronize();
            $options = $this->optionSynchronizationPool->executeForAttributeCodes(
                $import['attribute_codes']
            );

            return [
                'import' => $import,
                'mapping' => $mapping,
                'options' => $options,
                'completion' => $this->outcomePolicy->completed($mapping, $options['summary']),
            ];
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }
}
