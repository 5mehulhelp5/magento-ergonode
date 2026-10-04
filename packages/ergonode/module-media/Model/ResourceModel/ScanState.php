<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ResourceModel;

use Ergonode\Media\Model\Port\ScanStateInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

class ScanState implements ScanStateInterface
{
    private const string TABLE = 'ergonode_media_scan';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function read(): array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow($connection->select()->from($this->table())->where('state_id = ?', 1));
        $row = is_array($row) ? $row : [];
        return [
            'status' => (string)($row['status'] ?? 'required'),
            'estimated_total' => (int)($row['estimated_total'] ?? 0),
            'indexed' => (int)($row['indexed'] ?? 0),
            'reused' => (int)($row['reused'] ?? 0),
            'removed' => (int)($row['removed'] ?? 0),
            'bytes' => (int)($row['bytes'] ?? 0),
            'started_at' => isset($row['started_at']) ? (int)$row['started_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (int)$row['updated_at'] : null,
            'last_completed_at' => isset($row['last_completed_at']) ? (int)$row['last_completed_at'] : null,
            'verification_completed_at' => isset($row['verification_completed_at']) ? (int)$row['verification_completed_at'] : null,
            'error' => $row['error'] ?? null,
        ];
    }

    public function estimate(): int
    {
        $connection = $this->resource->getConnection();
        return (int)$connection->fetchOne($connection->select()->from(
            $this->resource->getTableName('catalog_product_entity_media_gallery'),
            ['total' => new Expression('COUNT(DISTINCT BINARY value)')]
        )->where('media_type = ?', 'image'));
    }

    public function request(int $estimate, bool $verifyContent = false): void
    {
        $this->write([
            'status' => $verifyContent ? 'audit_pending' : 'pending', 'estimated_total' => $estimate,
            'indexed' => 0, 'reused' => 0, 'removed' => 0, 'bytes' => 0,
            'started_at' => null, 'error' => null,
        ]);
    }

    public function begin(int $estimate, bool $verifyContent = false): void
    {
        $this->write([
            'status' => $verifyContent ? 'auditing' : 'running', 'estimated_total' => $estimate,
            'indexed' => 0, 'reused' => 0, 'removed' => 0, 'bytes' => 0,
            'started_at' => time(), 'error' => null,
        ]);
    }

    public function progress(array $counts, int $bytes): void
    {
        $this->write($counts + ['bytes' => $bytes]);
    }

    public function complete(bool $verifyContent = false, ?string $report = null): void
    {
        if ($verifyContent) {
            // An audit reports findings but does not change import readiness or expected asset hashes.
            $this->write(['status' => 'audited', 'verification_completed_at' => time(), 'error' => $report]);
            return;
        }
        $this->write(['status' => 'complete', 'last_completed_at' => time(), 'error' => null]);
    }

    public function fail(string $error): void
    {
        $this->write(['status' => 'failed', 'error' => mb_substr($error, 0, 4000)]);
    }

    /** @param array<string, int|string|null> $data */
    private function write(array $data): void
    {
        $data['updated_at'] = time();
        $this->resource->getConnection()->insertOnDuplicate(
            $this->table(),
            [['state_id' => 1] + $data],
            array_keys($data)
        );
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
