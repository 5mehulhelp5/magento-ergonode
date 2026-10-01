<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Zend_Db_Expr;

class AttributeCacheWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly OptionSnapshotCache $snapshotCache,
        private readonly ErgonodeAttributeProvider $attributeProvider
    ) {
    }

    /**
     * @param array<int, array{
     *     code: string,
     *     type: string,
     *     scope: string,
     *     labels: array<string, string>,
     *     parameters: array<string, bool|string>,
     *     hash: string
     * }> $attributes
     * @return array<string, 'inserted'|'updated'|'unchanged'>
     */
    public function saveAttributes(array $attributes): array
    {
        if ($attributes === []) {
            return [];
        }

        $this->snapshotCache->reset();
        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_attribute');
        $existingHashes = $this->loadHashes($table, 'code', array_column($attributes, 'code'));
        $rows = [];
        $results = [];

        foreach ($attributes as $attribute) {
            $code = $attribute['code'];
            $result = $this->resolveResult($existingHashes, $code, $attribute['hash']);
            $results[$code] = $result;
            if ($result === 'unchanged') {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'type' => $attribute['type'],
                'scope' => $attribute['scope'],
                'labels_json' => $this->json->serialize($attribute['labels']),
                'parameters_json' => $this->json->serialize($attribute['parameters']),
                'content_hash' => $attribute['hash'],
                'synced_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
            ];
        }

        if ($rows !== []) {
            $connection->insertOnDuplicate(
                $table,
                $rows,
                [
                    'type',
                    'scope',
                    'labels_json',
                    'parameters_json',
                    'content_hash',
                    'synced_at',
                ]
            );
            $this->attributeProvider->reset();
        }

        return $results;
    }

    /**
     * @param array<int, array{
     *     code: string,
     *     labels: array<string, string>,
     *     sort_order: int,
     *     hash: string
     * }> $options
     * @return array<string, 'inserted'|'updated'|'unchanged'>
     */
    public function saveOptions(string $attributeCode, array $options): array
    {
        if ($options === []) {
            return [];
        }

        $this->snapshotCache->reset();
        $connection = $this->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_attribute_option');
        $codes = array_column($options, 'code');
        $existingHashes = $this->loadHashes(
            $table,
            'option_code',
            $codes,
            ['attribute_code = ?' => $attributeCode]
        );
        $rows = [];
        $results = [];

        foreach ($options as $option) {
            $code = $option['code'];
            $result = $this->resolveResult($existingHashes, $code, $option['hash']);
            $results[$code] = $result;
            if ($result === 'unchanged') {
                continue;
            }

            $rows[] = [
                'attribute_code' => $attributeCode,
                'option_code' => $code,
                'sort_order' => $option['sort_order'],
                'labels_json' => $this->json->serialize($option['labels']),
                'content_hash' => $option['hash'],
                'synced_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
            ];
        }

        if ($rows !== []) {
            $connection->insertOnDuplicate(
                $table,
                $rows,
                ['sort_order', 'labels_json', 'content_hash', 'synced_at']
            );
        }

        return $results;
    }

    /**
     * @param string[] $keys
     * @param array<string, mixed> $conditions
     * @return array<string, string>
     */
    private function loadHashes(string $table, string $keyColumn, array $keys, array $conditions = []): array
    {
        $select = $this->getConnection()->select()
            ->from($table, [$keyColumn, 'content_hash'])
            ->where($keyColumn . ' IN (?)', array_values(array_unique($keys)));
        foreach ($conditions as $condition => $value) {
            $select->where($condition, $value);
        }

        $hashes = [];
        foreach ($this->getConnection()->fetchAll($select) as $row) {
            $hashes[(string)$row[$keyColumn]] = (string)$row['content_hash'];
        }

        return $hashes;
    }

    /**
     * @param array<string, string> $existingHashes
     * @return 'inserted'|'updated'|'unchanged'
     */
    private function resolveResult(array $existingHashes, string $key, string $hash): string
    {
        if (!isset($existingHashes[$key])) {
            return 'inserted';
        }

        return $existingHashes[$key] === $hash ? 'unchanged' : 'updated';
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
