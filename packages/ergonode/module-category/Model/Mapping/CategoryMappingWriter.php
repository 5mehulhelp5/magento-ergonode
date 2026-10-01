<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Mapping;

use Ergonode\Category\Api\CategoryRemoteIdentityWriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class CategoryMappingWriter implements CategoryRemoteIdentityWriterInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function saveLayout(
        int $categoryTreeId,
        string $code,
        ?string $manualParentCode,
        ?int $manualSortOrder,
        ?int $magentoCategoryId
    ): void {
        if ($categoryTreeId <= 0 || trim($code) === '') {
            return;
        }

        $this->connection()->insertOnDuplicate($this->table(), [[
            'category_tree_id' => $categoryTreeId,
            'ergonode_category_code' => trim($code),
            'manual_parent_code' => $manualParentCode,
            'manual_sort_order' => $manualSortOrder,
            'magento_category_id' => $magentoCategoryId,
            'sync_status' => 'pending',
            'sync_message' => null,
        ]], [
            'manual_parent_code',
            'manual_sort_order',
            'magento_category_id',
            'sync_status',
            'sync_message',
        ]);
    }

    public function saveRemoteIdentities(int $categoryTreeId, array $identities): void
    {
        if ($categoryTreeId <= 0 || $identities === []) {
            return;
        }

        $rows = [];
        foreach ($identities as $identity) {
            $code = trim((string)($identity['code'] ?? ''));
            $remoteId = trim((string)($identity['remote_id'] ?? ''));
            if ($code === '' || $remoteId === '') {
                continue;
            }
            $rows[] = [
                'category_tree_id' => $categoryTreeId,
                'ergonode_category_code' => $code,
                'ergonode_category_id' => $remoteId,
                'manual_parent_code' => $identity['manual_parent_code'] ?? null,
                'manual_sort_order' => $identity['manual_sort_order'] ?? null,
                'magento_category_id' => $identity['magento_category_id'] ?? null,
                'sync_status' => 'pending',
                'sync_message' => null,
            ];
        }
        if ($rows === []) {
            return;
        }

        $this->connection()->insertOnDuplicate($this->table(), $rows, [
            'ergonode_category_id',
            'manual_parent_code',
            'manual_sort_order',
            'magento_category_id',
            'sync_status',
            'sync_message',
        ]);
    }

    public function updateMagentoLink(
        int $categoryTreeId,
        string $code,
        int $categoryId,
        string $status = 'synced',
        ?string $message = null
    ): void {
        if ($categoryTreeId <= 0 || trim($code) === '' || $categoryId <= 0) {
            return;
        }

        $this->connection()->insertOnDuplicate($this->table(), [[
            'category_tree_id' => $categoryTreeId,
            'ergonode_category_code' => trim($code),
            'magento_category_id' => $categoryId,
            'sync_status' => $status,
            'sync_message' => $message,
        ]], [
            'magento_category_id',
            'sync_status',
            'sync_message',
        ]);
    }

    public function updateSyncStatus(int $categoryTreeId, string $code, string $status, ?string $message = null): void
    {
        if ($categoryTreeId <= 0 || trim($code) === '') {
            return;
        }

        $this->connection()->insertOnDuplicate($this->table(), [[
            'category_tree_id' => $categoryTreeId,
            'ergonode_category_code' => trim($code),
            'sync_status' => $status,
            'sync_message' => $message,
        ]], ['sync_status', 'sync_message']);
    }

    public function markMagentoCategoryDeletedById(int $categoryTreeId, int $categoryId): void
    {
        if ($categoryTreeId <= 0 || $categoryId <= 0) {
            return;
        }

        $this->connection()->update(
            $this->table(),
            [
                'magento_category_id' => null,
                'sync_status' => 'missing',
                'sync_message' => (string)__('Magento category #%1 was removed after reconciliation.', $categoryId),
            ],
            [
                'category_tree_id = ?' => $categoryTreeId,
                'magento_category_id = ?' => $categoryId,
            ]
        );
    }

    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName('ergonode_category_mapping');
    }
}
