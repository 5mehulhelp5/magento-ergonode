<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ResourceModel;

use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Throwable;
use Zend_Db_Adapter_Abstract;

class ProductImportWorkRepository implements ProductImportWorkRepositoryInterface
{
    private const string TABLE = 'ergonode_product_import_item';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly DateTime $dateTime,
        private readonly ?\Psr\Log\LoggerInterface $logger = null
    ) {
    }

    /** @param array<int, array{sku: string, payload: array<string, mixed>}> $items */
    public function scheduleSynchronizations(array $items): int
    {
        return $this->schedule($items, ProductImportWorkItem::OPERATION_SYNCHRONIZE);
    }

    /** @param array<int, array{sku: string, payload: null}> $items */
    public function scheduleDeletions(array $items): int
    {
        return $this->schedule($items, ProductImportWorkItem::OPERATION_DELETE);
    }

    public function countActive(): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(), [new Expression('COUNT(*)')])
                ->where('status IN (?)', ['pending', 'processing'])
        );
    }

    /** @return ProductImportWorkItem[] */
    public function claim(int $limit, int $leaseSeconds): array
    {
        if ($limit < 1 || $leaseSeconds < 1) {
            return [];
        }
        $connection = $this->connection();
        $now = $this->now();
        $leaseToken = random_bytes(16);
        $connection->beginTransaction();
        try {
            $this->expireInterruptedWork();
            $pending = $connection->quoteInto('status = ?', 'pending')
                . ' AND ' . $connection->quoteInto('available_at <= ?', $now);
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($this->table())
                    ->where('(' . $pending . ') AND attempt_count = 0')
                    ->order('item_id ASC')
                    ->limit($limit)
                    ->forUpdate(true)
            );
            if ($rows === []) {
                $connection->commit();
                return [];
            }
            $itemIds = array_map(static fn (array $row): int => (int)$row['item_id'], $rows);
            $connection->update($this->table(), [
                'status' => 'processing',
                'attempt_count' => new Expression('attempt_count + 1'),
                'lease_token' => $leaseToken,
                'lease_expires_at' => $this->dateTime->gmtDate(null, time() + $leaseSeconds),
                'last_error' => null,
            ], ['item_id IN (?)' => $itemIds]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return array_map(
            fn (array $row): ProductImportWorkItem => new ProductImportWorkItem(
                (int)$row['item_id'],
                (string)$row['ergonode_sku'],
                (string)$row['operation'],
                $this->decodePayload($row['payload_json'] ?? null),
                (string)$row['event_token'],
                $leaseToken,
                (int)$row['attempt_count'] + 1
            ),
            $rows
        );
    }

    public function complete(ProductImportWorkItem $item): bool
    {
        return $this->connection()->delete($this->table(), [
            'item_id = ?' => $item->itemId,
            'status = ?' => 'processing',
            'event_token = ?' => $item->eventToken,
            'lease_token = ?' => $item->leaseToken,
        ]) === 1;
    }

    public function release(
        ProductImportWorkItem $item,
        string $message,
        int $maximumAttempts,
        int $delaySeconds,
        bool $dependencyWait = false
    ): void {
        $failed = true;
        $this->connection()->update($this->table(), [
            'status' => $failed ? 'failed' : 'pending',
            'available_at' => $this->now(),
            'lease_token' => null,
            'lease_expires_at' => null,
            'last_error' => mb_substr(trim($message), 0, 65535),
        ], [
            'item_id = ?' => $item->itemId,
            'status = ?' => 'processing',
            'event_token = ?' => $item->eventToken,
            'lease_token = ?' => $item->leaseToken,
        ]);
    }

    public function hasClaimableWork(): bool
    {
        $connection = $this->connection();
        $now = $this->now();
        $pending = $connection->quoteInto('status = ?', 'pending')
            . ' AND ' . $connection->quoteInto('available_at <= ?', $now);

        return (bool)$connection->fetchOne(
            $connection->select()
                ->from($this->table(), [new Expression('1')])
                ->where('(' . $pending . ') AND attempt_count = 0')
                ->limit(1)
        );
    }

    private function expireInterruptedWork(?array $skus = null): void
    {
        $db = $this->connection();
        $condition = "(status = 'processing' AND lease_expires_at IS NOT NULL AND "
            . $db->quoteInto('lease_expires_at <= ?', $this->now()) . ") OR (status = 'pending' AND attempt_count > 0)";
        $select = $db->select()->from($this->table(), ['item_id', 'ergonode_sku', 'event_token'])->where($condition);
        if ($skus !== null) { $select->where('ergonode_sku IN (?)', $skus); }
        $rows = $db->fetchAll($select->forUpdate(true));
        foreach ($rows as $row) {
            $message = 'Product import was interrupted. Start a new import including this product.';
            $affected = $db->update($this->table(), ['status' => 'failed', 'lease_token' => null,
                'lease_expires_at' => null, 'last_error' => $message], [
                    'item_id = ?' => $row['item_id'], 'event_token = ?' => $row['event_token'], 0 => $condition,
                ]);
            if ($affected > 0) {
                $this->logger?->error($message, ['ergonode_sku' => $row['ergonode_sku'], 'item_id' => (int)$row['item_id']]);
            }
        }
    }

    /** @param array<int, array{sku: string, payload: array<string, mixed>|null}> $items */
    private function schedule(array $items, string $operation): int
    {
        if ($items === []) { return 0; }
        $skus = array_map(static fn(array $item): string => trim((string)($item['sku'] ?? '')), $items);
        foreach ($skus as $sku) {
            if ($sku === '' || strlen($sku) > 64) {
                throw new LocalizedException(__('Ergonode product stream returned an invalid SKU.'));
            }
        }
        $this->expireInterruptedWork(array_values(array_unique($skus)));
        $scheduled = 0;
        $now = $this->now();
        foreach ($items as $item) {
            $sku = trim((string)($item['sku'] ?? ''));
            if ($sku === '' || strlen($sku) > 64) {
                throw new LocalizedException(__('Ergonode product stream returned an invalid SKU.'));
            }
            $payload = $item['payload'] ?? null;
            $this->connection()->insertOnDuplicate($this->table(), [[
                'ergonode_sku' => $sku,
                'operation' => $operation,
                'payload_json' => is_array($payload) ? $this->json->serialize($payload) : null,
                'event_token' => random_bytes(16),
                'status' => 'pending',
                'attempt_count' => 0,
                'available_at' => $now,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => null,
                'updated_at' => $now,
            ]], [
                'operation',
                'payload_json',
                'event_token',
                'status',
                'attempt_count',
                'available_at',
                'lease_token',
                'lease_expires_at',
                'last_error',
                'updated_at',
            ]);
            $scheduled++;
        }

        return $scheduled;
    }

    /** @return array<string, mixed>|null */
    private function decodePayload(mixed $payload): ?array
    {
        if (!is_string($payload) || $payload === '') {
            return null;
        }
        $decoded = $this->json->unserialize($payload);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return AdapterInterface&Zend_Db_Adapter_Abstract */
    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE);
    }

    private function now(): string
    {
        return (string)$this->dateTime->gmtDate();
    }
}
