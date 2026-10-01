<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use Ergonode\Category\Api\CategoryRemoteIdentityProviderInterface;
use Magento\Framework\App\ResourceConnection;

class CategoryRemoteIdentityProvider implements CategoryRemoteIdentityProviderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getIdsByCode(int $categoryTreeId, array $codes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map('trim', $codes),
            static fn (string $code): bool => $code !== ''
        )));
        if ($categoryTreeId <= 0 || $codes === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_category_mapping'),
                    ['ergonode_category_code', 'ergonode_category_id']
                )
                ->where('category_tree_id = ?', $categoryTreeId)
                ->where('ergonode_category_code IN (?)', $codes)
                ->where('ergonode_category_id IS NOT NULL')
        );
        $result = [];
        foreach ($rows as $code => $remoteId) {
            $code = trim((string)$code);
            $remoteId = trim((string)$remoteId);
            if ($code !== '' && $remoteId !== '') {
                $result[$code] = $remoteId;
            }
        }

        return $result;
    }
}
