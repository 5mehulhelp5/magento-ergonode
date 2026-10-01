<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Mapping;

use Ergonode\Language\Api\LanguageStoreMappingRowsProviderInterface;
use Magento\Framework\App\ResourceConnection;

class LanguageStoreMappingRowsProvider implements LanguageStoreMappingRowsProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getRows(): array
    {
        $table = $this->resourceConnection->getTableName('ergonode_language_store_mapping');
        $rows = $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()
                ->select()
                ->from($table, ['mapping_id', 'store_id', 'language_code', 'sort_order', 'is_manual'])
                ->order('sort_order ASC')
                ->order('mapping_id ASC')
        );

        return array_values(array_map(
            static function (array $row): array {
                $languageCode = trim((string)($row['language_code'] ?? ''));

                return [
                    'mapping_id' => (int)$row['mapping_id'],
                    'store_id' => $row['store_id'] !== null ? (int)$row['store_id'] : null,
                    'language_code' => $languageCode !== '' ? $languageCode : null,
                    'sort_order' => (int)$row['sort_order'],
                    'is_manual' => (int)$row['is_manual'],
                ];
            },
            $rows
        ));
    }
}
