<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\ResourceModel;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Model\Data\ProductIdentity;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class ProductIdentityService implements ProductIdentityServiceInterface
{
    private const string TABLE = 'ergonode_product_mapping';

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getIdentitiesByProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }

        return $this->identities('mapping.product_id IN (?)', $productIds, true);
    }

    public function getIdentitiesByErgonodeSkus(array $ergonodeSkus): array
    {
        $skus = $this->normalizeSkus($ergonodeSkus);
        if ($skus === []) {
            return [];
        }

        return $this->identities('mapping.ergonode_sku IN (?)', $skus, false);
    }

    public function getErgonodeSkusByProductIds(array $productIds): array
    {
        return array_map(
            static fn (ProductIdentityInterface $identity): string => $identity->getErgonodeSku(),
            $this->getIdentitiesByProductIds($productIds)
        );
    }

    public function bind(int $productId, string $ergonodeSku, string $identityMode): void
    {
        $this->bindMany([$productId => [
            'ergonode_sku' => $ergonodeSku,
            'identity_mode' => $identityMode,
        ]]);
    }

    public function bindMany(array $identities): void
    {
        $identities = $this->normalizeIdentities($identities);
        if ($identities === []) {
            return;
        }
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $existingProductIds = $this->assertIdentitiesAvailable($identities);
            $now = gmdate('Y-m-d H:i:s');
            $rows = [];
            foreach ($identities as $productId => $identity) {
                if (isset($existingProductIds[$productId])) {
                    continue;
                }
                $this->assertNewIdentityMode($identity['identity_mode']);
                $rows[] = [
                    'product_id' => $productId,
                    'ergonode_sku' => $identity['ergonode_sku'],
                    'identity_mode' => $identity['identity_mode'],
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                $connection->insertMultiple($this->table(), $rows);
            }
            $connection->commit();
        } catch (LocalizedException $exception) {
            $connection->rollBack();
            throw $exception;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw new LocalizedException(__('Unable to persist the Ergonode product identity mapping.'), $exception);
        }
    }

    public function getImportHash(int $productId): ?string
    {
        if ($productId < 1) {
            return null;
        }

        $hash = $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(), ['import_hash'])
                ->where('product_id = ?', $productId)
                ->limit(1)
        );

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function recordImported(int $productId, string $ergonodeSku, string $contentHash): void
    {
        $ergonodeSku = trim($ergonodeSku);
        $contentHash = strtolower(trim($contentHash));
        if ($productId < 1 || $ergonodeSku === '' || preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new LocalizedException(__('Invalid Ergonode product identity import state.'));
        }

        $existing = $this->getIdentitiesByProductIds([$productId]);
        if ($existing === []) {
            throw new LocalizedException(__('Bind the Ergonode product identity before recording its import.'));
        } elseif ($existing[$productId]->getErgonodeSku() !== $ergonodeSku) {
            $this->bind(
                $productId,
                $ergonodeSku,
                $existing[$productId]->getIdentityMode()
            );
        }
        $now = gmdate('Y-m-d H:i:s');
        try {
            $this->connection()->insertOnDuplicate($this->table(), [[
                'product_id' => $productId,
                'ergonode_sku' => $ergonodeSku,
                'import_hash' => $contentHash,
                'imported_at' => $now,
                'updated_at' => $now,
            ]], ['import_hash', 'imported_at', 'updated_at']);
        } catch (Throwable $exception) {
            throw new LocalizedException(__('Unable to persist the Ergonode product identity mapping.'), $exception);
        }
        $identity = $this->getIdentitiesByProductIds([$productId])[$productId] ?? null;
        if ($identity === null || $identity->getErgonodeSku() !== $ergonodeSku) {
            throw new LocalizedException(__('Unable to verify the Ergonode product identity mapping.'));
        }
    }

    public function clearImportHash(string $ergonodeSku): void
    {
        $ergonodeSku = trim($ergonodeSku);
        if ($ergonodeSku === '') {
            return;
        }
        $this->connection()->update($this->table(), [
            'import_hash' => null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['ergonode_sku = ?' => $ergonodeSku]);
    }

    /**
     * @param array<int, array{ergonode_sku: string, identity_mode: string}> $identities
     * @return array<int, true>
     */
    private function assertIdentitiesAvailable(array $identities): array
    {
        $productIds = array_keys($identities);
        $ergonodeSkus = array_column($identities, 'ergonode_sku');
        $condition = $this->connection()->quoteInto('product_id IN (?)', $productIds)
            . ' OR ' . $this->connection()->quoteInto('ergonode_sku IN (?)', $ergonodeSkus);
        $select = $this->connection()->select()
            ->from($this->table(), ['product_id', 'ergonode_sku', 'identity_mode'])
            ->where($condition);
        $select->forUpdate(true);
        $matched = [];
        foreach ($this->connection()->fetchAll($select) as $row) {
            $productId = (int)$row['product_id'];
            $expected = $identities[$productId] ?? null;
            if ($expected === null
                || (string)$row['ergonode_sku'] !== $expected['ergonode_sku']
                || (string)$row['identity_mode'] !== $expected['identity_mode']
            ) {
                throw new LocalizedException(__(
                    'Ergonode SKU "%1" conflicts with an existing immutable Magento product identity.',
                    (string)$row['ergonode_sku']
                ));
            }
            $matched[$productId] = true;
        }

        return $matched;
    }

    private function assertNewIdentityMode(string $mode): void
    {
        if ($mode === ProductIdentityInterface::MODE_SHARED) {
            throw new LocalizedException(__('New Ergonode product bindings require assigned or mapped SKU mode.'));
        }
    }

    /**
     * @param array<int, array{ergonode_sku: string, identity_mode: string}> $identities
     * @return array<int, array{ergonode_sku: string, identity_mode: string}>
     */
    private function normalizeIdentities(array $identities): array
    {
        $normalized = [];
        $seenSkus = [];
        foreach ($identities as $productId => $identity) {
            $productId = (int)$productId;
            $ergonodeSku = is_array($identity) && is_string($identity['ergonode_sku'] ?? null)
                ? trim($identity['ergonode_sku'])
                : '';
            $identityMode = is_array($identity) && is_string($identity['identity_mode'] ?? null)
                ? $identity['identity_mode']
                : '';
            if ($productId < 1
                || $ergonodeSku === ''
                || isset($seenSkus['sku:' . $ergonodeSku])
                || !in_array($identityMode, [
                    ProductIdentityInterface::MODE_SHARED,
                    ProductIdentityInterface::MODE_ASSIGNED,
                    ProductIdentityInterface::MODE_MAPPED,
                ], true)
            ) {
                throw new LocalizedException(__('Invalid Ergonode product identity mapping.'));
            }
            $seenSkus['sku:' . $ergonodeSku] = true;
            $normalized[$productId] = [
                'ergonode_sku' => $ergonodeSku,
                'identity_mode' => $identityMode,
            ];
        }

        return $normalized;
    }

    /** @return array<int, ProductIdentityInterface> */
    private function identities(string $condition, array $values, bool $indexByProductId): array
    {
        $rows = $this->connection()->fetchAll(
            $this->connection()->select()
                ->from(['mapping' => $this->table()], ['product_id', 'ergonode_sku', 'identity_mode'])
                ->join(
                    ['product' => $this->resourceConnection->getTableName('catalog_product_entity')],
                    'product.entity_id = mapping.product_id',
                    ['magento_sku' => 'sku']
                )
                ->where($condition, $values)
        );
        $result = [];
        foreach ($rows as $row) {
            $identity = new ProductIdentity(
                (int)$row['product_id'],
                (string)$row['magento_sku'],
                (string)$row['ergonode_sku'],
                (string)$row['identity_mode']
            );
            if ($indexByProductId) {
                $result[$identity->getProductId()] = $identity;
            } else {
                $result[] = $identity;
            }
        }

        return $result;
    }

    /** @param string[] $skus @return string[] */
    private function normalizeSkus(array $skus): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $sku): string => is_string($sku) ? trim($sku) : '',
            $skus
        ))));
    }

    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE);
    }
}
