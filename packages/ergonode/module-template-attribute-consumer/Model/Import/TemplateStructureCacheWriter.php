<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;
use Zend_Db_Expr;

class TemplateStructureCacheWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    /**
     * @param array<int, array{
     *     code: string,
     *     sections: array<int, array{
     *         code: string,
     *         is_synthetic: bool,
     *         sort_order: int,
     *         attributes: array<int, array{code: string, sort_order: int}>,
     *         raw: array<string, mixed>,
     *         hash: string
     *     }>
     * }> $templates
     * @return array<string, 'inserted'|'updated'|'unchanged'>
     */
    public function save(array $templates): array
    {
        if ($templates === []) {
            return [];
        }

        $connection = $this->getConnection();
        $templateCodes = array_column($templates, 'code');
        $existingHashes = $this->loadStructureHashes($templateCodes);
        $existingGroupIds = $this->loadGroupIds($templateCodes);
        $results = [];
        $changedTemplates = [];

        foreach ($templates as $template) {
            $code = $template['code'];
            $hash = $this->structureHash($template);
            $result = !isset($existingHashes[$code])
                ? 'inserted'
                : ($existingHashes[$code] === $hash ? 'unchanged' : 'updated');
            $results[$code] = $result;
            if ($result !== 'unchanged') {
                $changedTemplates[] = $template;
            }
        }

        $connection->beginTransaction();
        try {
            $this->replaceStructures($changedTemplates, $existingGroupIds);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $results;
    }

    /**
     * @param string[] $templateCodes
     * @return array<string, string>
     */
    private function loadStructureHashes(array $templateCodes): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_template_section'),
                    ['template_code', 'section_code', 'content_hash']
                )
                ->where('template_code IN (?)', array_values(array_unique($templateCodes)))
                ->order(['template_code ASC', 'sort_order ASC', 'section_code ASC'])
        );

        $hashParts = [];
        foreach ($rows as $row) {
            $hashParts[(string)$row['template_code']][] = [
                (string)$row['section_code'],
                (string)$row['content_hash'],
            ];
        }

        $hashes = [];
        foreach ($hashParts as $templateCode => $parts) {
            $hashes[$templateCode] = hash('sha256', $this->json->serialize($parts));
        }

        return $hashes;
    }

    /**
     * @param string[] $templateCodes
     * @return array<string, int>
     */
    private function loadGroupIds(array $templateCodes): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_template_section'),
                    ['template_code', 'section_code', 'attribute_group_id']
                )
                ->where('template_code IN (?)', array_values(array_unique($templateCodes)))
                ->where('attribute_group_id IS NOT NULL')
        );

        $groupIds = [];
        foreach ($rows as $row) {
            $groupIds[(string)$row['template_code'] . '::' . (string)$row['section_code']]
                = (int)$row['attribute_group_id'];
        }

        return $groupIds;
    }

    /**
     * @param array<int, array{code: string, sections: array<int, array<string, mixed>>}> $templates
     * @param array<string, int> $existingGroupIds
     */
    private function replaceStructures(array $templates, array $existingGroupIds): void
    {
        if ($templates === []) {
            return;
        }

        $connection = $this->getConnection();
        $sectionTable = $this->resourceConnection->getTableName('ergonode_template_section');
        $attributeTable = $this->resourceConnection->getTableName('ergonode_template_attribute');
        $templateCodes = array_column($templates, 'code');
        $connection->delete($attributeTable, ['template_code IN (?)' => $templateCodes]);
        $connection->delete($sectionTable, ['template_code IN (?)' => $templateCodes]);

        $sections = [];
        $attributes = [];
        foreach ($templates as $template) {
            foreach ($template['sections'] as $section) {
                $sectionCode = (string)$section['code'];
                $sections[] = [
                    'template_code' => $template['code'],
                    'section_code' => $sectionCode,
                    'attribute_group_id' => $existingGroupIds[$template['code'] . '::' . $sectionCode] ?? null,
                    'is_synthetic' => !empty($section['is_synthetic']) ? 1 : 0,
                    'sort_order' => (int)$section['sort_order'],
                    'content_hash' => (string)$section['hash'],
                    'raw_json' => $this->json->serialize($section['raw']),
                    'synced_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                ];
                foreach ($section['attributes'] as $attribute) {
                    $attributes[] = [
                        'template_code' => $template['code'],
                        'section_code' => $sectionCode,
                        'attribute_code' => (string)$attribute['code'],
                        'sort_order' => (int)$attribute['sort_order'],
                    ];
                }
            }
        }

        if ($sections !== []) {
            $connection->insertMultiple($sectionTable, $sections);
        }
        if ($attributes !== []) {
            $connection->insertMultiple($attributeTable, $attributes);
        }
    }

    /** @param array{sections: array<int, array{code: string, hash: string}>} $template */
    private function structureHash(array $template): string
    {
        $parts = [];
        foreach ($template['sections'] as $section) {
            $parts[] = [$section['code'], $section['hash']];
        }

        return hash('sha256', $this->json->serialize($parts));
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
