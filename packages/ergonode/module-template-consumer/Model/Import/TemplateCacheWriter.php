<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Zend_Db_Expr;

class TemplateCacheWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    /**
     * @param array<int, array{code: string, raw: array<string, mixed>, hash: string}> $templates
     * @return array<string, 'inserted'|'updated'|'unchanged'>
     */
    public function save(array $templates): array
    {
        if ($templates === []) {
            return [];
        }

        $table = $this->resourceConnection->getTableName('ergonode_template');
        $existingHashes = $this->loadHashes($table, array_column($templates, 'code'));
        $rows = [];
        $results = [];
        foreach ($templates as $template) {
            $result = !isset($existingHashes[$template['code']])
                ? 'inserted'
                : ($existingHashes[$template['code']] === $template['hash'] ? 'unchanged' : 'updated');
            $results[$template['code']] = $result;
            if ($result === 'unchanged') {
                continue;
            }
            $rows[] = [
                'code' => $template['code'],
                'is_deleted' => 0,
                'content_hash' => $template['hash'],
                'raw_json' => $this->json->serialize($template['raw']),
                'synced_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
            ];
        }

        if ($rows !== []) {
            $this->getConnection()->insertOnDuplicate(
                $table,
                $rows,
                ['content_hash', 'raw_json', 'synced_at', 'is_deleted']
            );
        }
        $this->getConnection()->update(
            $table,
            ['is_deleted' => 0],
            ['code IN (?)' => array_values(array_unique(array_column($templates, 'code')))]
        );

        return $results;
    }

    /**
     * @param string[] $activeTemplateCodes
     * @return string[]
     */
    public function markMissingAsDeleted(array $activeTemplateCodes): array
    {
        $activeTemplateCodes = array_values(array_unique(array_filter(
            array_map(static fn (string $code): string => trim($code), $activeTemplateCodes)
        )));
        $table = $this->resourceConnection->getTableName('ergonode_template');
        $select = $this->getConnection()->select()->from($table, ['code'])->where('is_deleted = ?', 0);
        if ($activeTemplateCodes !== []) {
            $select->where('code NOT IN (?)', $activeTemplateCodes);
        }
        $deletedTemplateCodes = array_map('strval', $this->getConnection()->fetchCol($select));
        if ($deletedTemplateCodes !== []) {
            $this->getConnection()->update(
                $table,
                ['is_deleted' => 1],
                ['code IN (?)' => $deletedTemplateCodes, 'is_deleted = ?' => 0]
            );
        }

        return $deletedTemplateCodes;
    }

    /** @return string[] */
    public function getDeletedCodes(): array
    {
        return array_map('strval', $this->getConnection()->fetchCol(
            $this->getConnection()->select()
                ->from($this->resourceConnection->getTableName('ergonode_template'), ['code'])
                ->where('is_deleted = ?', 1)
                ->order('code ASC')
        ));
    }

    /**
     * @param string[] $codes
     * @return array<string, string>
     */
    private function loadHashes(string $table, array $codes): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()->select()->from($table, ['code', 'content_hash'])->where('code IN (?)', $codes)
        );
        $hashes = [];
        foreach ($rows as $row) {
            $hashes[(string)$row['code']] = (string)$row['content_hash'];
        }

        return $hashes;
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
