<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\Category\Api\CategoryRemoteIdentityProviderInterface;
use Ergonode\Category\Api\CategoryRemoteIdentityWriterInterface;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Throwable;

class CategoryRemoteIdentityResolver
{
    public function __construct(
        private readonly CategoryRemoteIdentityProviderInterface $identityProvider,
        private readonly CategoryRemoteIdentityWriterInterface $identityWriter,
        private readonly CategoryTreeGateway $gateway
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, string>
     */
    public function resolve(int $categoryTreeId, array $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        $idsByCode = $this->identityProvider->getIdsByCode($categoryTreeId, $codes);
        $resolvedIdentities = [];
        $missing = [];
        foreach ($items as $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code !== '' && !isset($idsByCode[$code])) {
                $missing[$code] = $item;
            }
        }
        if ($missing === []) {
            return $idsByCode;
        }
        try {
            foreach ($this->gateway->getCategoryIds(array_map('strval', array_keys($missing))) as $code => $remoteId) {
                $idsByCode[$code] = $remoteId;
                $resolvedIdentities[] = $this->identity($missing[$code], (string)$code, $remoteId);
            }
        } catch (Throwable $exception) {
            $this->identityWriter->saveRemoteIdentities($categoryTreeId, $resolvedIdentities);
            throw $exception;
        }
        $this->identityWriter->saveRemoteIdentities($categoryTreeId, $resolvedIdentities);

        return $idsByCode;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *     code: string,
     *     remote_id: string,
     *     manual_parent_code: string|null,
     *     manual_sort_order: int,
     *     magento_category_id: int|null
     * }
     */
    private function identity(array $item, string $code, string $remoteId): array
    {
        $parentCode = trim((string)($item['parent_code'] ?? ''));

        return [
            'code' => $code,
            'remote_id' => $remoteId,
            'manual_parent_code' => $parentCode !== '' ? $parentCode : null,
            'manual_sort_order' => max(0, (int)($item['sort_order'] ?? 0)),
            'magento_category_id' => (int)($item['magento_category_id'] ?? 0) ?: null,
        ];
    }
}
