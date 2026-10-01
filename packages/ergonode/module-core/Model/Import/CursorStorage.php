<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Zend_Db_Expr;

class CursorStorage
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /** @return array{cursor: string|null, synced_at: string|null}|null */
    public function get(string $processCode): ?array
    {
        $processCode = trim($processCode);
        if ($processCode === '') {
            return null;
        }

        $row = $this->getConnection()->fetchRow(
            $this->getConnection()
                ->select()
                ->from($this->getTable(), ['cursor', 'synced_at'])
                ->where('process_code = ?', $processCode)
                ->limit(1)
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'cursor' => $row['cursor'] !== null ? (string)$row['cursor'] : null,
            'synced_at' => $row['synced_at'] !== null ? (string)$row['synced_at'] : null,
        ];
    }

    public function acquireLease(
        string $processCode,
        string $lockToken,
        int $leaseSeconds
    ): bool {
        $processCode = trim($processCode);
        $lockToken = trim($lockToken);
        if ($processCode === '' || $lockToken === '') {
            return false;
        }

        $connection = $this->getConnection();
        $table = $this->getTable();
        $connection->insertOnDuplicate(
            $table,
            [
                'process_code' => $processCode,
                'cursor' => null,
            ],
            ['process_code']
        );

        return $connection->update(
            $table,
            [
                'lock_token' => $lockToken,
                'started_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                'completed_at' => null,
            ],
            [
                'process_code = ?' => $processCode,
                '(lock_token IS NULL OR started_at IS NULL OR started_at <= ?)' => gmdate(
                    'Y-m-d H:i:s',
                    time() - max(1, $leaseSeconds)
                ),
            ]
        ) === 1;
    }

    public function clearCursor(string $processCode, string $lockToken): void
    {
        $this->getConnection()->update(
            $this->getTable(),
            ['cursor' => null, 'reset_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP')],
            [
                'process_code = ?' => $processCode,
                'lock_token = ?' => $lockToken,
            ]
        );
    }

    public function completeLease(
        string $processCode,
        string $lockToken,
        ?string $cursor
    ): bool {
        return $this->getConnection()->update(
            $this->getTable(),
            [
                'cursor' => $cursor,
                'lock_token' => null,
                'synced_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                'completed_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
            ],
            [
                'process_code = ?' => $processCode,
                'lock_token = ?' => $lockToken,
            ]
        ) === 1;
    }

    public function releaseLease(string $processCode, string $lockToken): void
    {
        $this->getConnection()->update(
            $this->getTable(),
            [
                'lock_token' => null,
                'completed_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
            ],
            [
                'process_code = ?' => $processCode,
                'lock_token = ?' => $lockToken,
            ]
        );
    }

    public function save(string $processCode, ?string $cursor): void
    {
        $processCode = trim($processCode);
        if ($processCode === '') {
            return;
        }

        $connection = $this->getConnection();
        $table = $this->getTable();
        $existing = $this->get($processCode);
        $data = [
            'process_code' => $processCode,
            'cursor' => $cursor,
            'synced_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
        ];

        if (!$existing) {
            $connection->insert($table, $data);
            return;
        }

        if ((string)($existing['cursor'] ?? '') === (string)$cursor) {
            return;
        }

        $connection->update($table, $data, ['process_code = ?' => $processCode]);
    }

    public function reset(string $processCode): void
    {
        $processCode = trim($processCode);
        if ($processCode === '') {
            return;
        }

        $this->getConnection()->insertOnDuplicate(
            $this->getTable(),
            [
                'process_code' => $processCode,
                'cursor' => null,
                'reset_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
            ],
            ['cursor', 'reset_at']
        );
    }

    public function getResetAt(string $processCode): ?string
    {
        $value = $this->getConnection()->fetchOne(
            $this->getConnection()->select()->from($this->getTable(), ['reset_at'])
                ->where('process_code = ?', $processCode)->limit(1)
        );

        return is_string($value) ? $value : null;
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function getTable(): string
    {
        return $this->resourceConnection->getTableName('ergonode_import_cursor');
    }
}
