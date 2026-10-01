<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Model;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\TemplateAttribute\Api\StructureMetadataContributorInterface;
use Ergonode\TemplateAttribute\Api\StructureProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class StructureProvider implements StructureProviderInterface
{
    /** @param StructureMetadataContributorInterface[] $metadataContributors */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MappingReaderInterface $mappingReader,
        private readonly array $metadataContributors = []
    ) {
    }

    public function get(string $templateCode, int $attributeSetId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $template = $connection->fetchRow($connection->select()
            ->from($this->table('ergonode_template'), ['code', 'attribute_set_id'])
            ->where('code = ?', $templateCode)->where('attribute_set_id = ?', $attributeSetId));
        $set = $connection->fetchRow($connection->select()
            ->from(['sets' => $this->table('eav_attribute_set')], ['attribute_set_name'])
            ->joinInner(
                ['types' => $this->table('eav_entity_type')],
                'types.entity_type_id = sets.entity_type_id',
                []
            )
            ->where('types.entity_type_code = ?', 'catalog_product')
            ->where('sets.attribute_set_id = ?', $attributeSetId));
        if (!is_array($template) || !is_array($set)) {
            throw new LocalizedException(__('The saved template mapping is no longer available. Reload the view.'));
        }

        $structure = [
            'template' => $templateCode,
            'attributeSet' => (string)$set['attribute_set_name'],
            'sections' => $connection->fetchAll($connection->select()
                ->from($this->table('ergonode_template_section'), ['section_code', 'raw_json', 'attribute_group_id'])
                ->where('template_code = ?', $templateCode)->order('sort_order ASC')->order('section_code ASC')),
            'sourceAttributes' => $connection->fetchAll($connection->select()
                ->from($this->table('ergonode_template_attribute'), ['section_code', 'attribute_code'])
                ->where('template_code = ?', $templateCode)->order('sort_order ASC')->order('attribute_code ASC')),
            'groups' => $connection->fetchAll($connection->select()
                ->from($this->table('eav_attribute_group'), ['attribute_group_id', 'attribute_group_name'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->order('sort_order ASC')->order('attribute_group_id ASC')),
            'attributes' => $this->attributes($attributeSetId),
        ];
        foreach ($structure['groups'] as &$group) {
            $group['ergonode_section_codes'] = array_column(array_filter(
                $structure['sections'],
                static fn (array $section): bool => (int)$section['attribute_group_id']
                    === (int)$group['attribute_group_id']
            ), 'section_code');
            $group['managed_by_ergonode'] = false;
        }
        unset($group);
        foreach ($this->metadataContributors as $contributor) {
            $structure = $contributor->contribute($templateCode, $attributeSetId, $structure);
        }

        return $structure;
    }

    /** @return array<int, array<string, mixed>> */
    private function attributes(int $attributeSetId): array
    {
        $connection = $this->resourceConnection->getConnection();

        $attributes = $connection->fetchAll($connection->select()
            ->from(
                ['attributes' => $this->table('eav_attribute')],
                ['attribute_id', 'attribute_code', 'frontend_label', 'frontend_input', 'is_required', 'is_user_defined']
            )
            ->joinInner(
                ['types' => $this->table('eav_entity_type')],
                'types.entity_type_id = attributes.entity_type_id',
                []
            )
            ->joinLeft(
                ['placement' => $this->table('eav_entity_attribute')],
                'placement.attribute_id = attributes.attribute_id AND '
                . $connection->quoteInto('placement.attribute_set_id = ?', $attributeSetId),
                ['attribute_group_id']
            )
            ->where('types.entity_type_code = ?', 'catalog_product')
            ->order('placement.sort_order ASC')->order('attributes.attribute_code ASC'));
        $codes = [];
        foreach ($this->mappingReader->getAttributeRows() as $mapping) {
            if ($mapping['status'] === 'complete' && !empty($mapping['ergonode_attribute_code'])
                && !empty($mapping['magento_attribute_code'])
            ) {
                $codes[$mapping['magento_attribute_code']][] = $mapping['ergonode_attribute_code'];
            }
        }
        foreach ($attributes as &$attribute) {
            $attribute['ergonode_attribute_codes'] = $codes[$attribute['attribute_code']] ?? [];
        }
        unset($attribute);

        return $attributes;
    }

    private function table(string $name): string
    {
        return $this->resourceConnection->getTableName($name);
    }
}
