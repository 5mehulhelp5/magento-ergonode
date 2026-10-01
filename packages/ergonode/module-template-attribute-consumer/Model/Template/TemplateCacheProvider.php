<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Template;

use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider as BaseTemplateCacheProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class TemplateCacheProvider
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly TemplateSectionNameResolver $sectionNameResolver,
        private readonly BaseTemplateCacheProvider $baseTemplateCacheProvider
    ) {
    }

    /**
     * @return array{
     *     entity_id: int,
     *     code: string,
     *     attribute_set_id: int|null,
     *     is_deleted: bool,
     *     sections: array<int, array{
     *         entity_id: int,
     *         code: string,
     *         name: string,
     *         is_synthetic: bool,
     *         attribute_group_id: int|null,
     *         sort_order: int,
     *         attributes: array<int, array{code: string, sort_order: int}>
     *     }>
     * }|null
     */
    public function getTemplateStructure(string $templateCode): ?array
    {
        $template = $this->baseTemplateCacheProvider->getTemplate($templateCode);
        if ($template === null) {
            return null;
        }

        $sections = [];
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()->select()
                ->from($this->resourceConnection->getTableName('ergonode_template_section'))
                ->where('template_code = ?', $template['code'])
                ->order('sort_order ASC')
                ->order('entity_id ASC')
        );
        foreach ($rows as $row) {
            $sectionCode = (string)$row['section_code'];
            $sections[$sectionCode] = [
                'entity_id' => (int)$row['entity_id'],
                'code' => $sectionCode,
                'name' => $this->sectionNameResolver->resolve((string)$row['raw_json'], $sectionCode),
                'is_synthetic' => (bool)$row['is_synthetic'],
                'attribute_group_id' => $row['attribute_group_id'] !== null
                    ? (int)$row['attribute_group_id']
                    : null,
                'sort_order' => (int)$row['sort_order'],
                'attributes' => [],
            ];
        }

        if ($sections !== []) {
            $attributes = $this->getConnection()->fetchAll(
                $this->getConnection()->select()
                    ->from(
                        $this->resourceConnection->getTableName('ergonode_template_attribute'),
                        ['section_code', 'attribute_code', 'sort_order']
                    )
                    ->where('template_code = ?', $template['code'])
                    ->order('section_code ASC')
                    ->order('sort_order ASC')
            );
            foreach ($attributes as $attribute) {
                $sectionCode = (string)$attribute['section_code'];
                if (isset($sections[$sectionCode])) {
                    $sections[$sectionCode]['attributes'][] = [
                        'code' => (string)$attribute['attribute_code'],
                        'sort_order' => (int)$attribute['sort_order'],
                    ];
                }
            }
        }

        return $template + ['sections' => array_values($sections)];
    }

    public function saveSectionGroupId(string $templateCode, string $sectionCode, int $attributeGroupId): void
    {
        $this->getConnection()->update(
            $this->resourceConnection->getTableName('ergonode_template_section'),
            ['attribute_group_id' => $attributeGroupId],
            ['template_code = ?' => $templateCode, 'section_code = ?' => $sectionCode]
        );
    }

    /** @return array<int, array{entity_id: int, code: string, attribute_set_id: int|null, is_deleted: bool}> */
    public function getAllTemplates(): array
    {
        return $this->baseTemplateCacheProvider->getAllTemplates();
    }

    /** @return array<int, array{entity_id: int, code: string, attribute_set_id: int, is_deleted: bool}> */
    public function getTemplatesWithAttributeSet(): array
    {
        return $this->baseTemplateCacheProvider->getTemplatesWithAttributeSet();
    }

    public function clearStaleAttributeSetMapping(string $templateCode, int $attributeSetId): bool
    {
        return $this->baseTemplateCacheProvider->clearStaleAttributeSetMapping($templateCode, $attributeSetId);
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
