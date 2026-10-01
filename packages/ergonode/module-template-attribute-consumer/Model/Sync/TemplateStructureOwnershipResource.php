<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class TemplateStructureOwnershipResource
{
    private const string GROUP_TABLE = 'ergonode_template_group_ownership';
    private const string ATTRIBUTE_TABLE = 'ergonode_template_attribute_ownership';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function saveGroupOwnership(
        string $templateCode,
        int $attributeSetId,
        string $sectionCode,
        int $attributeGroupId
    ): void {
        $this->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName(self::GROUP_TABLE),
            [[
                'template_code' => $templateCode,
                'attribute_set_id' => $attributeSetId,
                'section_code' => $sectionCode,
                'attribute_group_id' => $attributeGroupId,
            ]],
            ['template_code', 'attribute_set_id', 'section_code', 'attribute_group_id']
        );
    }

    public function saveAttributeOwnership(
        string $templateCode,
        int $attributeSetId,
        string $ergonodeAttributeCode,
        int $magentoAttributeId,
        int $attributeGroupId,
        int $entityAttributeId
    ): void {
        $this->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName(self::ATTRIBUTE_TABLE),
            [[
                'template_code' => $templateCode,
                'attribute_set_id' => $attributeSetId,
                'ergonode_attribute_code' => $ergonodeAttributeCode,
                'magento_attribute_id' => $magentoAttributeId,
                'attribute_group_id' => $attributeGroupId,
                'entity_attribute_id' => $entityAttributeId,
            ]],
            [
                'template_code',
                'attribute_set_id',
                'ergonode_attribute_code',
                'magento_attribute_id',
                'attribute_group_id',
                'entity_attribute_id',
            ]
        );
    }

    /**
     * @return array{attribute_group_id: int, template_code: string|null, section_code: string|null}|null
     */
    public function findGroupCodeOwner(int $attributeSetId, string $groupCode): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()
                ->select()
                ->from(
                    ['attribute_group' => $this->resourceConnection->getTableName('eav_attribute_group')],
                    ['attribute_group_id']
                )
                ->joinLeft(
                    ['ownership' => $this->resourceConnection->getTableName(self::GROUP_TABLE)],
                    'ownership.attribute_group_id = attribute_group.attribute_group_id',
                    ['template_code', 'section_code']
                )
                ->where('attribute_group.attribute_set_id = ?', $attributeSetId)
                ->where('attribute_group.attribute_group_code = ?', $groupCode)
                ->limit(1)
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'attribute_group_id' => (int)$row['attribute_group_id'],
            'template_code' => isset($row['template_code']) ? (string)$row['template_code'] : null,
            'section_code' => isset($row['section_code']) ? (string)$row['section_code'] : null,
        ];
    }

    /**
     * @return array{template_code: string, attribute_set_id: int, section_code: string}|null
     */
    public function findGroupOwnerById(int $attributeGroupId): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName(self::GROUP_TABLE),
                    ['template_code', 'attribute_set_id', 'section_code']
                )
                ->where('attribute_group_id = ?', $attributeGroupId)
                ->limit(1)
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'template_code' => (string)$row['template_code'],
            'attribute_set_id' => (int)$row['attribute_set_id'],
            'section_code' => (string)$row['section_code'],
        ];
    }

    /**
     * @return int[]
     */
    public function loadAttributeSetIds(string $templateCode): array
    {
        $attributeSetIds = [];
        foreach ([self::GROUP_TABLE, self::ATTRIBUTE_TABLE] as $table) {
            $ids = $this->getConnection()->fetchCol(
                $this->getConnection()
                    ->select()
                    ->distinct()
                    ->from($this->resourceConnection->getTableName($table), ['attribute_set_id'])
                    ->where('template_code = ?', $templateCode)
            );
            foreach ($ids as $attributeSetId) {
                $attributeSetIds[(int)$attributeSetId] = true;
            }
        }

        $result = array_map('intval', array_keys($attributeSetIds));
        sort($result);

        return $result;
    }

    /**
     * @return array<int, array{
     *     ownership_id: int,
     *     section_code: string,
     *     attribute_group_id: int,
     *     attribute_group_code: string,
     *     attribute_group_name: string
     * }>
     */
    public function loadGroupOwnerships(string $templateCode, int $attributeSetId): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(
                    ['ownership' => $this->resourceConnection->getTableName(self::GROUP_TABLE)],
                    [
                        'ownership_id' => 'entity_id',
                        'section_code',
                        'attribute_group_id',
                    ]
                )
                ->joinInner(
                    ['attribute_group' => $this->resourceConnection->getTableName('eav_attribute_group')],
                    'attribute_group.attribute_group_id = ownership.attribute_group_id',
                    ['attribute_group_code', 'attribute_group_name']
                )
                ->where('ownership.template_code = ?', $templateCode)
                ->where('ownership.attribute_set_id = ?', $attributeSetId)
                ->order('ownership.entity_id ASC')
        );

        return array_map(
            static fn (array $row): array => [
                'ownership_id' => (int)$row['ownership_id'],
                'section_code' => (string)$row['section_code'],
                'attribute_group_id' => (int)$row['attribute_group_id'],
                'attribute_group_code' => (string)$row['attribute_group_code'],
                'attribute_group_name' => (string)$row['attribute_group_name'],
            ],
            $rows
        );
    }

    /**
     * @return array<int, array{
     *     ownership_id: int,
     *     ergonode_attribute_code: string,
     *     magento_attribute_id: int,
     *     magento_attribute_code: string,
     *     is_user_defined: bool,
     *     is_required: bool,
     *     attribute_group_id: int,
     *     entity_attribute_id: int
     * }>
     */
    public function loadAttributeOwnerships(string $templateCode, int $attributeSetId): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(
                    ['ownership' => $this->resourceConnection->getTableName(self::ATTRIBUTE_TABLE)],
                    [
                        'ownership_id' => 'entity_id',
                        'ergonode_attribute_code',
                        'magento_attribute_id',
                        'attribute_group_id',
                        'entity_attribute_id',
                    ]
                )
                ->joinInner(
                    ['attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                    'attribute.attribute_id = ownership.magento_attribute_id',
                    ['magento_attribute_code' => 'attribute_code', 'is_user_defined', 'is_required']
                )
                ->where('ownership.template_code = ?', $templateCode)
                ->where('ownership.attribute_set_id = ?', $attributeSetId)
                ->order('ownership.entity_id ASC')
        );

        return array_map(
            static fn (array $row): array => [
                'ownership_id' => (int)$row['ownership_id'],
                'ergonode_attribute_code' => (string)$row['ergonode_attribute_code'],
                'magento_attribute_id' => (int)$row['magento_attribute_id'],
                'magento_attribute_code' => (string)$row['magento_attribute_code'],
                'is_user_defined' => (bool)$row['is_user_defined'],
                'is_required' => (bool)$row['is_required'],
                'attribute_group_id' => (int)$row['attribute_group_id'],
                'entity_attribute_id' => (int)$row['entity_attribute_id'],
            ],
            $rows
        );
    }

    public function deleteGroupOwnership(int $ownershipId): void
    {
        $this->getConnection()->delete(
            $this->resourceConnection->getTableName(self::GROUP_TABLE),
            ['entity_id = ?' => $ownershipId]
        );
    }

    public function deleteAttributeOwnership(int $ownershipId): void
    {
        $this->getConnection()->delete(
            $this->resourceConnection->getTableName(self::ATTRIBUTE_TABLE),
            ['entity_id = ?' => $ownershipId]
        );
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
