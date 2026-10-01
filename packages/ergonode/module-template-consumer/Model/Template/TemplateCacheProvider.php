<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Template;

use Ergonode\TemplateConsumer\Model\Mapping\TemplateAttributeSetMappingResource;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class TemplateCacheProvider
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AttributeSetResource $attributeSetResource,
        private readonly TemplateAttributeSetMappingResource $attributeSetMappingResource
    ) {
    }

    /** @return array{entity_id: int, code: string, attribute_set_id: int|null, is_deleted: bool}|null */
    public function getTemplate(string $templateCode): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()
                ->from($this->resourceConnection->getTableName('ergonode_template'))
                ->where('code = ?', trim($templateCode))
                ->limit(1)
        );

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return array<int, array{entity_id: int, code: string, attribute_set_id: int, is_deleted: bool}> */
    public function getTemplatesWithAttributeSet(): array
    {
        $templates = [];
        foreach ($this->getAllTemplates() as $template) {
            if ($template['attribute_set_id'] === null) {
                continue;
            }
            $templates[] = $template + ['attribute_set_id' => $template['attribute_set_id']];
        }

        return $templates;
    }

    /** @return array<int, array{entity_id: int, code: string, attribute_set_id: int|null, is_deleted: bool}> */
    public function getAllTemplates(bool $includeDeleted = false): array
    {
        $select = $this->getConnection()->select()
            ->from(
                $this->resourceConnection->getTableName('ergonode_template'),
                ['entity_id', 'code', 'attribute_set_id', 'is_deleted']
            )
            ->order('code ASC');
        if (!$includeDeleted) {
            $select->where('is_deleted = ?', 0);
        }

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $this->getConnection()->fetchAll($select)
        );
    }

    /** @throws LocalizedException */
    public function assignAttributeSet(string $templateCode, int $attributeSetId): void
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            throw new LocalizedException(__('Ergonode template code is required.'));
        }
        $this->attributeSetMappingResource->assign($templateCode, $attributeSetId);
    }

    public function clearStaleAttributeSetMapping(string $templateCode, int $attributeSetId): bool
    {
        if ($templateCode === '' || $attributeSetId <= 0) {
            return false;
        }
        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_template');
        $connection->beginTransaction();
        try {
            $template = $connection->fetchRow(
                $connection->select()
                    ->from($table, ['entity_id', 'attribute_set_id'])
                    ->where('code = ?', $templateCode)
                    ->limit(1)
                    ->forUpdate(true)
            );
            if (!is_array($template)
                || (int)($template['attribute_set_id'] ?? 0) !== $attributeSetId
                || $this->attributeSetResource->productAttributeSetExists($attributeSetId)
            ) {
                $connection->commit();

                return false;
            }
            $connection->update(
                $table,
                ['attribute_set_id' => null],
                ['entity_id = ?' => (int)$template['entity_id'], 'attribute_set_id = ?' => $attributeSetId]
            );
            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @return array{entity_id: int, code: string, attribute_set_id: int|null, is_deleted: bool} */
    private function normalizeRow(array $row): array
    {
        return [
            'entity_id' => (int)$row['entity_id'],
            'code' => (string)$row['code'],
            'attribute_set_id' => $row['attribute_set_id'] !== null ? (int)$row['attribute_set_id'] : null,
            'is_deleted' => (bool)$row['is_deleted'],
        ];
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
