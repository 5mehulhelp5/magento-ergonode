<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Snapshot;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class CategoryEntitySnapshotWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    /**
     * @param array{
     *     code: string,
     *     labels: array<string, string>,
     *     attributes: array<int, array<string, mixed>>,
     *     hash: string,
     *     raw: array<string, mixed>
     * } $entity
     * @return 'inserted'|'updated'|'unchanged'
     */
    public function save(array $entity): string
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->table();
        $existing = $connection->fetchRow(
            $connection->select()->from($table, ['content_hash'])->where('category_code = ?', $entity['code'])->limit(1)
        );
        if (is_array($existing) && (string)$existing['content_hash'] === $entity['hash']) {
            return 'unchanged';
        }
        $connection->insertOnDuplicate($table, [[
            'category_code' => $entity['code'],
            'labels_json' => $this->json->serialize($entity['labels']),
            'attributes_json' => $this->json->serialize($entity['attributes']),
            'content_hash' => $entity['hash'],
            'raw_json' => $this->json->serialize($entity['raw']),
        ]], ['labels_json', 'attributes_json', 'content_hash', 'raw_json']);

        return is_array($existing) ? 'updated' : 'inserted';
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName('ergonode_category_entity_snapshot');
    }
}
