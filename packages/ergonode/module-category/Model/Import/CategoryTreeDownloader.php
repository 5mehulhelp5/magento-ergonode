<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Throwable;

class CategoryTreeDownloader
{
    private const int MAX_PAGES = 1000;

    public function __construct(
        private readonly CategoryTreePageReader $pageReader,
        private readonly CategoryNormalizer $categoryNormalizer,
        private readonly PaginationStateResolver $paginationStateResolver,
        private readonly TreePageSizePolicy $pageSizePolicy,
        private readonly CategoryTreeDownloadScope $downloadScope
    ) {
    }

    /** @return array<string, mixed> */
    public function download(string $treeCode, bool $writeScope = false): array
    {
        return $this->downloadScope->tree(
            ($writeScope ? 'write:' : 'read:') . $treeCode,
            fn (): array => $this->read(
                $treeCode,
                fn (string $code, ?string $cursor, int $size): array => $writeScope
                    ? $this->pageReader->readWriteScope($code, $cursor, $size)
                    : $this->pageReader->read($code, $cursor, $size)
            )
        );
    }

    /**
     * @param callable(string, ?string, int): array{data: array<string, mixed>, seconds: float} $query
     * @return array{
     *     complete: true,
     *     pages: int,
     *     page_size: int,
     *     categories: array<int, array{
     *         code: string,
     *         parent_code: string|null,
     *         labels: array<string, string>,
     *         sort_order: int,
     *         raw: array<string, mixed>,
     *         hash: string
     *     }>,
     *     snapshot: array{inserted: int, updated: int, unchanged: int, removed: int}
     * }
     * @throws LocalizedException
     * @throws Throwable
     */
    private function read(string $treeCode, callable $query): array
    {
        $cursor = null;
        $pageSize = $this->pageSizePolicy->initialSize();
        $nodes = [];
        $pages = 0;

        do {
            if ($pages >= self::MAX_PAGES) {
                throw new LocalizedException(__('Ergonode category tree pagination exceeded the safety limit.'));
            }
            $requestedSize = $pageSize;
            $page = $query($treeCode, $cursor, $requestedSize);
            $data = $page['data'];
            $pageSize = (int)($data['_page_size'] ?? $requestedSize);
            if (array_key_exists('categoryTree', $data) && $data['categoryTree'] === null) {
                throw new MissingCategoryTreeException(
                    __(
                        'Ergonode category tree "%1" was not found. '
                        . 'Magento categories and mappings were preserved.',
                        $treeCode
                    )
                );
            }
            $remoteTree = $data['categoryTree'] ?? null;
            if (!is_array($remoteTree)) {
                throw new LocalizedException(__('Unable to verify the Ergonode category tree.'));
            }
            $remoteTreeCode = trim((string)($remoteTree['code'] ?? ''));
            if ($remoteTreeCode !== '' && $remoteTreeCode !== $treeCode) {
                throw new LocalizedException(
                    __('Ergonode returned an unexpected category tree "%1".', $remoteTreeCode)
                );
            }
            $connection = is_array($remoteTree['categoryTreeLeafList'] ?? null)
                ? $remoteTree['categoryTreeLeafList']
                : [];
            foreach ((array)($connection['edges'] ?? []) as $edge) {
                if (is_array($edge) && is_array($edge['node'] ?? null)) {
                    $nodes[] = $edge['node'];
                }
            }
            $pages++;
            $pageInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
            $pagination = $this->paginationStateResolver->resolve(
                $pageInfo,
                $cursor,
                __('Ergonode returned invalid category pagination.')
            );
            if ($pagination['has_more']) {
                $cursor = $pagination['cursor'];
                if ($pageSize === $requestedSize) {
                    $pageSize = $this->pageSizePolicy->nextSize(
                        $pageSize,
                        count((array)($connection['edges'] ?? [])),
                        $page['seconds']
                    );
                }
            }
        } while ($pagination['has_more']);

        $categories = [];
        foreach ($nodes as $node) {
            $category = $this->categoryNormalizer->normalizeTreeNode($node, count($categories));
            if ($category !== null) {
                $categories[] = $category;
            }
        }

        return [
            'complete' => true,
            'pages' => $pages,
            'page_size' => $pageSize,
            'categories' => $categories,
        ];
    }
}
