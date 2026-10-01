<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Api\CategoryTreeSnapshotUpdaterInterface;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class CategoryTreePublisher
{
    public function __construct(
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryTreeGateway $gateway,
        private readonly CategoryTreePayloadBuilder $payloadBuilder,
        private readonly CategoryTreeSnapshotUpdaterInterface $snapshotUpdater,
        private readonly CategoryRemoteIdentityResolver $identityResolver,
        private readonly PendingCategoryPublisher $pendingCategoryPublisher,
        private readonly CategoryPublicationDetails $publicationDetails
    ) {
    }

    /** @param array<int, array<string, mixed>> $items */
    public function requiresManualWrite(int $categoryTreeId, array $items): bool
    {
        $existing = $this->categoryCacheProvider->getRowsByCode($categoryTreeId);
        foreach ($items as $item) {
            $extension = is_array($item['extension_data']['to_ergonode'] ?? null)
                ? $item['extension_data']['to_ergonode'] : [];
            if (!empty($extension['pending_create']) || !empty($extension['remote_prepared'])) {
                return true;
            }
        }

        // Compare against the saved layout, including its existing manual overrides.
        // Snapshot traversal positions and Magento sibling positions are not comparable.
        $layout = array_map(static fn (array $row): array => [
            'code' => $row['code'],
            'parent_code' => $row['effective_parent_code'] ?? $row['parent_code'] ?? null,
            'sort_order' => $row['effective_sort_order'] ?? $row['sort_order'],
        ], array_values($existing));
        $codes = array_column($layout, 'code');
        $identities = array_combine($codes, $codes);

        return $this->payloadBuilder->build($items, $identities)
            !== $this->payloadBuilder->build($layout, $identities);
    }

    /** @param array<int, array<string, mixed>> $items */
    public function publish(int $categoryTreeId, array $items): void
    {
        $this->pendingCategoryPublisher->publish($categoryTreeId, $items);

        $treeConfig = $this->categoryTreeQuery->getById($categoryTreeId);
        $tree = $this->gateway->getTree((string)$treeConfig['tree_code']);
        $treeId = trim((string)($tree['id'] ?? ''));
        if ($treeId === '') {
            throw new LocalizedException(__('Ergonode did not return the category tree identifier.'));
        }

        $idsByCode = $this->identityResolver->resolve($categoryTreeId, $items);
        $remoteIds = $this->collectIds((array)($tree['categories'] ?? []));
        $unknownIds = array_diff($remoteIds, array_values($idsByCode));
        if ($unknownIds !== []) {
            throw new LocalizedException(__(
                'The Ergonode tree contains categories missing from this screen. Refresh the tree before saving.'
            ));
        }

        $payload = [
            'name' => $tree['name'] ?? [],
            'categories' => $this->payloadBuilder->build($items, $idsByCode),
        ];
        $this->gateway->updateTree($treeId, $payload);

        try {
            $layout = $this->payloadBuilder->snapshotLayout($payload['categories'], $idsByCode);
            $this->snapshotUpdater->update(
                $categoryTreeId,
                $layout,
                $this->publicationDetails->get($categoryTreeId)
            );
            $this->publicationDetails->clear($categoryTreeId, array_column($layout, 'code'));
        } catch (Throwable $exception) {
            throw new LocalizedException(__(
                'The Ergonode tree was updated, but Magento could not refresh its snapshot: %1',
                $exception->getMessage()
            ));
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    public function complete(int $categoryTreeId, array $items): void
    {
        $this->pendingCategoryPublisher->complete($categoryTreeId, $items);
    }

    /** @return string[] */
    private function collectIds(array $nodes): array
    {
        $ids = [];
        $this->indexIds($nodes, $ids);
        return array_map('strval', array_keys($ids));
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, bool> $ids
     */
    private function indexIds(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $id = trim((string)($node['category_id'] ?? $node['categoryId'] ?? ''));
            if ($id !== '') {
                $ids[$id] = true;
            }
            $this->indexIds((array)($node['children'] ?? []), $ids);
        }
    }
}
