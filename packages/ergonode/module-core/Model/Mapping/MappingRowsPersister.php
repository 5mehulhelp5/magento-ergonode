<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Mapping;

use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;

class MappingRowsPersister implements MappingRowsPersisterInterface
{
    public function __construct(private readonly Json $json)
    {
    }

    public function persist(
        AdapterInterface $connection,
        string $table,
        array $existing,
        array $normalized
    ): array {
        $stats = ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];

        foreach ($existing as $key => $row) {
            if (!isset($normalized[$key])) {
                $connection->delete($table, ['mapping_id = ?' => (int)$row['mapping_id']]);
                $stats['deleted']++;
            }
        }

        foreach ($normalized as $key => $mapping) {
            if (!isset($existing[$key])) {
                $connection->insert($table, $mapping);
                $stats['inserted']++;
                continue;
            }

            if ((string)$existing[$key]['content_hash'] === (string)$mapping['content_hash']
                && (int)$existing[$key]['sort_order'] === (int)$mapping['sort_order']
            ) {
                $stats['unchanged']++;
                continue;
            }

            $connection->update($table, $mapping, ['mapping_id = ?' => (int)$existing[$key]['mapping_id']]);
            $stats['updated']++;
        }

        return $stats;
    }

    public function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', $this->json->serialize($payload));
    }
}
