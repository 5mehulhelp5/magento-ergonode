<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Sync;

use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingPersister;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoOptionCreator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class MagentoOptionSyncResource
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductAttributeConfigProvider $attributeConfigProvider,
        private readonly MagentoOptionCreator $magentoOptionCreator,
        private readonly OptionMappingPersister $optionMappingPersister
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadErgonodeOptions(string $attributeCode): array
    {
        return $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('ergonode_attribute_option'))
                ->where('attribute_code = ?', $attributeCode)
                ->order('sort_order ASC')
                ->order('entity_id ASC')
        );
    }

    /**
     * @return array{by_id: array<int, array<string, mixed>>, by_label: array<string, int>}
     */
    public function loadMagentoOptions(int $attributeId): array
    {
        $optionTable = $this->resourceConnection->getTableName('eav_attribute_option');
        $valueTable = $this->resourceConnection->getTableName('eav_attribute_option_value');
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(['option' => $optionTable], ['option_id', 'sort_order'])
                ->joinLeft(
                    ['value' => $valueTable],
                    'value.option_id = option.option_id AND value.store_id = 0',
                    ['label' => 'value']
                )
                ->where('option.attribute_id = ?', $attributeId)
        );
        $result = [
            'by_id' => [],
            'by_label' => [],
        ];

        foreach ($rows as $row) {
            $optionId = (int)$row['option_id'];
            $label = (string)($row['label'] ?? '');
            $labelKey = mb_strtolower(trim($label));
            $result['by_id'][$optionId] = [
                'option_id' => $optionId,
                'sort_order' => (int)$row['sort_order'],
                'label' => $label,
                'store_labels' => [],
            ];
            if ($labelKey !== '') {
                $result['by_label'][$labelKey] = $optionId;
            }
        }

        if ($result['by_id'] !== []) {
            $storeRows = $this->getConnection()->fetchAll(
                $this->getConnection()->select()
                    ->from(['option' => $optionTable], [])
                    ->joinInner(
                        ['value' => $valueTable],
                        'value.option_id = option.option_id',
                        ['option_id', 'store_id', 'value']
                    )
                    ->where('option.attribute_id = ?', $attributeId)
                    ->where('value.store_id > ?', 0)
            );
            foreach ($storeRows as $row) {
                $optionId = (int)$row['option_id'];
                if (isset($result['by_id'][$optionId])) {
                    $result['by_id'][$optionId]['store_labels'][(int)$row['store_id']] = (string)$row['value'];
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>> Rows keyed by Ergonode option code.
     */
    public function loadExistingMappings(int $attributeMappingId): array
    {
        return $this->optionMappingPersister->loadByOptionCode($attributeMappingId);
    }

    /**
     * @return array{
     *     option_id: int,
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     source: string,
     *     created: bool
     * }
     */
    public function createMagentoOption(string $attributeCode, string $label, int $sortOrder): array
    {
        return $this->magentoOptionCreator->create($attributeCode, $label, $sortOrder);
    }

    /**
     * @param array{
     *     by_id: array<int, array<string, mixed>>,
     *     by_label?: array<string, int>,
     *     by_match?: array<string, array<int, int>>
     * } $magentoOptions
     */
    public function syncSortOrder(int $optionId, int $sortOrder, array &$magentoOptions): bool
    {
        if (!$this->attributeConfigProvider->shouldSynchronizeOptionSortOrder()) {
            return false;
        }

        $current = isset($magentoOptions['by_id'][$optionId]['sort_order'])
            ? (int)$magentoOptions['by_id'][$optionId]['sort_order']
            : null;
        if ($current === $sortOrder) {
            return false;
        }

        $this->getConnection()->update(
            $this->resourceConnection->getTableName('eav_attribute_option'),
            ['sort_order' => $sortOrder],
            ['option_id = ?' => $optionId]
        );
        $magentoOptions['by_id'][$optionId]['sort_order'] = $sortOrder;

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>> $existingMappings
     * @return 'inserted'|'updated'|'unchanged'
     */
    public function syncMapping(
        int $attributeMappingId,
        string $ergonodeOptionCode,
        int $magentoOptionId,
        int $sortOrder,
        array &$existingMappings
    ): string {
        return $this->optionMappingPersister->upsertComplete(
            $attributeMappingId,
            $ergonodeOptionCode,
            $magentoOptionId,
            $sortOrder,
            $existingMappings
        );
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
