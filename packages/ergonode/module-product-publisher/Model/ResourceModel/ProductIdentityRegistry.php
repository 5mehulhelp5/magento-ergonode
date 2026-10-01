<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\ResourceModel;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\Exception\ProductIdentityConflictException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class ProductIdentityRegistry implements ProductIdentityRegistryInterface
{
    private const string TABLE = 'ergonode_product_mapping';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly MagentoIdentityAttributeInterface $identityAttribute
    ) {
    }

    public function getIdentitiesByProductIds(array $productIds): array
    {
        return $this->identityService->getIdentitiesByProductIds($productIds);
    }

    public function getIdentitiesByErgonodeSkus(array $skus): array
    {
        return $this->identityService->getIdentitiesByErgonodeSkus($skus);
    }

    public function getMappedSkuValuesByProductIds(array $productIds): array
    {
        return $this->identityAttribute->getValuesByProductIds($productIds);
    }

    public function findMappedSkuProductIds(array $skus): array
    {
        return $this->identityAttribute->findProductIds($skus);
    }

    public function getMappedSkuAttributeCode(): string
    {
        return $this->identityAttribute->getCode();
    }

    public function assertStable(array $productSkus): void
    {
        $failures = $this->getStabilityFailures($productSkus);
        if ($failures !== []) {
            throw new ProductIdentityConflictException(__((string)reset($failures)));
        }
    }

    public function getStabilityFailures(array $productSkus): array
    {
        $productSkus = $this->normalize($productSkus);
        if ($productSkus === []) {
            return [];
        }
        $failures = [];
        foreach ($this->getIdentitiesByProductIds(array_keys($productSkus)) as $productId => $identity) {
            if ($identity->getIdentityMode() !== ProductIdentityInterface::MODE_SHARED
                || $productSkus[(int)$productId] === $identity->getErgonodeSku()
            ) {
                continue;
            }
            $failures[(int)$productId] = (string)__(
                'Magento product ID %1 was published as immutable Ergonode SKU "%2" and now uses SKU "%3". '
                . 'Resolve the identity mapping before publishing it again.',
                (int)$productId,
                $identity->getErgonodeSku(),
                $productSkus[(int)$productId]
            );
        }

        return $failures;
    }

    public function recordPublished(array $productSkus): void
    {
        $productSkus = $this->normalize($productSkus);
        if ($productSkus === []) {
            return;
        }
        $identities = $this->getIdentitiesByProductIds(array_keys($productSkus));
        $this->assertStableAgainstIdentities($productSkus, $identities);
        $now = gmdate('Y-m-d H:i:s');
        $rows = [];
        foreach ($productSkus as $productId => $sku) {
            $identity = $identities[$productId] ?? null;
            if ($identity === null) {
                throw new ProductIdentityConflictException(__(
                    'Magento product ID %1 has no assigned or mapped Ergonode SKU binding.',
                    $productId
                ));
            }
            $rows[] = [
                'product_id' => $productId,
                'ergonode_sku' => $identity?->getErgonodeSku() ?? $sku,
                'published_at' => $now,
                'updated_at' => $now,
            ];
        }
        try {
            $this->resourceConnection->getConnection()->insertOnDuplicate(
                $this->resourceConnection->getTableName(self::TABLE),
                $rows,
                ['published_at', 'updated_at']
            );
        } catch (Throwable $exception) {
            throw new LocalizedException(
                __('Unable to persist the Ergonode product identity mapping.'),
                $exception
            );
        }
    }

    public function bindAssignedBatch(array $productSkus): void
    {
        $identities = [];
        foreach ($this->normalize($productSkus) as $productId => $ergonodeSku) {
            $identities[$productId] = [
                'ergonode_sku' => $ergonodeSku,
                'identity_mode' => ProductIdentityInterface::MODE_ASSIGNED,
            ];
        }
        $this->identityService->bindMany($identities);
    }

    public function bindMappedBatch(array $productSkus): void
    {
        $identities = [];
        foreach ($this->normalize($productSkus) as $productId => $ergonodeSku) {
            $identities[$productId] = [
                'ergonode_sku' => $ergonodeSku,
                'identity_mode' => ProductIdentityInterface::MODE_MAPPED,
            ];
        }
        $this->identityService->bindMany($identities);
    }

    /** @param array<int, string> $productSkus @return array<int, string> */
    private function normalize(array $productSkus): array
    {
        $normalized = [];
        foreach ($productSkus as $productId => $sku) {
            $productId = (int)$productId;
            $sku = is_string($sku) ? trim($sku) : '';
            if ($productId < 1 || $sku === '') {
                throw new LocalizedException(__('Product identity mappings require positive IDs and non-empty SKUs.'));
            }
            $normalized[$productId] = $sku;
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $productSkus
     * @param array<int, ProductIdentityInterface> $identities
     */
    private function assertStableAgainstIdentities(array $productSkus, array $identities): void
    {
        foreach ($identities as $productId => $identity) {
            if ($identity->getIdentityMode() !== ProductIdentityInterface::MODE_SHARED
                || $productSkus[(int)$productId] === $identity->getErgonodeSku()
            ) {
                continue;
            }
            throw new ProductIdentityConflictException(__(
                'Magento product ID %1 was published as immutable Ergonode SKU "%2" and now uses SKU "%3". '
                . 'Resolve the identity mapping before publishing it again.',
                (int)$productId,
                $identity->getErgonodeSku(),
                $productSkus[(int)$productId]
            ));
        }
    }
}
