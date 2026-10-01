<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\CategoryTree;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Zend_Db_Expr;

class CategoryTreeOptionSyncer
{
    private const int MAX_PAGES = 200;

    public function __construct(
        private readonly Client $client,
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    /**
     * @return array{synced: int, removed: int, codes: string[]}
     * @throws LocalizedException
     */
    public function sync(): array
    {
        $cursor = null;
        $seenCursors = [];
        $rows = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $data = $this->client->query(CategoryQueries::CATEGORY_TREE_STREAM, [
                'first' => 100,
                'after' => $cursor,
            ]);
            $stream = isset($data['categoryTreeStream']) && is_array($data['categoryTreeStream'])
                ? $data['categoryTreeStream']
                : [];
            $edges = isset($stream['edges']) && is_array($stream['edges']) ? $stream['edges'] : [];
            $nextCursor = null;

            foreach ($edges as $edge) {
                if (!is_array($edge)) {
                    continue;
                }

                $node = isset($edge['node']) && is_array($edge['node']) ? $edge['node'] : [];
                $code = trim((string)($node['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $rows[$code] = [
                    'code' => $code,
                    'labels_json' => $this->json->serialize([$code => $code]),
                    'raw_json' => $this->json->serialize($node),
                    'synced_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                ];
                $nextCursor = isset($edge['cursor']) ? (string)$edge['cursor'] : $nextCursor;
            }

            if ($nextCursor === null || $nextCursor === $cursor || isset($seenCursors[$nextCursor])) {
                break;
            }

            $seenCursors[$nextCursor] = true;
            $cursor = $nextCursor;
        }

        if (!$rows) {
            throw new LocalizedException(__('Ergonode did not return any category trees.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_tree_option');
        $codes = array_keys($rows);
        $existingCodes = $connection->fetchCol(
            $connection->select()
                ->from($table, ['code'])
        );
        $removed = array_values(array_diff($existingCodes, $codes));

        $connection->beginTransaction();
        try {
            if ($removed) {
                $connection->delete($table, ['code IN (?)' => $removed]);
            }

            $connection->insertOnDuplicate(
                $table,
                array_values($rows),
                ['labels_json', 'raw_json', 'synced_at']
            );
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }

            throw new LocalizedException(__('Unable to save Ergonode category tree options.'));
        }

        sort($codes);

        return [
            'synced' => count($codes),
            'removed' => count($removed),
            'codes' => $codes,
        ];
    }
}
