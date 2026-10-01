<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualRest;

use Magento\Framework\Exception\LocalizedException;

class CategoryTreeGateway
{
    private const int CATEGORY_PAGE_SIZE = 50;

    public function __construct(
        private readonly Client $client,
        private readonly CategoryTreeIdentityCache $identityCache
    ) {
    }

    /** @return array<string, mixed> */
    public function getTree(string $treeCode): array
    {
        $cachedId = $this->identityCache->get($treeCode);
        if ($cachedId !== null) {
            try {
                $tree = $this->loadTree($cachedId);
                if ($this->matchesCode($tree, $treeCode)) {
                    return $tree;
                }
            } catch (RequestException $exception) {
                if ($exception->getStatusCode() !== 404) {
                    throw $exception;
                }
            }
            $this->identityCache->remove($treeCode);
        }
        $list = $this->client->get('trees?' . http_build_query([
            'limit' => 50,
            'offset' => 0,
            'filter' => 'code=' . $treeCode,
            'view' => 'list',
        ]));
        $treeId = $this->findEntityId($list, $treeCode);
        if ($treeId === null) {
            throw new LocalizedException(__('Ergonode category tree "%1" was not found.', $treeCode));
        }
        $tree = $this->loadTree($treeId);
        if (!$this->matchesCode($tree, $treeCode)) {
            throw new LocalizedException(__('Ergonode category tree "%1" was not found.', $treeCode));
        }
        $this->identityCache->save($treeCode, $treeId);

        return $tree;
    }

    /** @return array<string, mixed> */
    private function loadTree(string $treeId): array
    {
        $tree = $this->client->get('trees/' . rawurlencode($treeId));
        $tree['id'] = $tree['id'] ?? $treeId;

        return $tree;
    }

    /** @param array<string, mixed> $tree */
    private function matchesCode(array $tree, string $treeCode): bool
    {
        return !isset($tree['code']) || $tree['code'] === $treeCode;
    }

    public function requireCategoryId(string $code): string
    {
        $response = $this->client->get('categories?' . http_build_query([
            'limit' => 50,
            'offset' => 0,
            'filter' => 'code=' . $code,
            'view' => 'list',
        ]));
        $id = $this->findEntityId($response, $code);
        if ($id === null) {
            throw new RetryableRequestException(
                (string)__('Ergonode category "%1" is not visible in REST yet. Retrying shortly.', $code),
                2
            );
        }

        return $id;
    }

    /**
     * Yield matches immediately so the caller can checkpoint them if a later request fails.
     * Recent pages cover newly created batches; sparse older codes use targeted lookups.
     *
     * @param string[] $codes
     * @return iterable<string, string>
     */
    public function getCategoryIds(array $codes): iterable
    {
        $missing = array_fill_keys($codes, true);
        $offset = 0;
        while (count($missing) > 1) {
            $response = $this->client->get('categories?' . http_build_query([
                'limit' => self::CATEGORY_PAGE_SIZE,
                'offset' => $offset,
                'field' => 'sequence',
                'order' => 'DESC',
                'view' => 'list',
            ]));
            $ids = $this->indexEntityIds($response);
            $matches = array_intersect_key($ids, $missing);
            foreach ($matches as $code => $id) {
                unset($missing[$code]);
                yield $code => $id;
            }
            if ($missing === []) {
                return;
            }
            $total = $response['info']['filtered'] ?? null;
            $remainingPages = is_int($total)
                ? (int)ceil(max(0, $total - $offset - self::CATEGORY_PAGE_SIZE) / self::CATEGORY_PAGE_SIZE)
                : 0;
            // Use the grid count only when finishing pagination costs fewer requests than individual lookups.
            $cheaperScan = $remainingPages > 0 && $remainingPages < count($missing);
            if (count($ids) < self::CATEGORY_PAGE_SIZE || ($matches === [] && !$cheaperScan)) {
                break;
            }
            $offset += self::CATEGORY_PAGE_SIZE;
        }
        foreach (array_keys($missing) as $code) {
            yield (string)$code => $this->requireCategoryId((string)$code);
        }
    }

    /** @param array<string|int, mixed> $response @return array<string, string> */
    private function indexEntityIds(array $response): array
    {
        $ids = [];
        $code = is_string($response['code'] ?? null) ? $response['code'] : '';
        $id = is_string($response['id'] ?? null) ? trim($response['id']) : '';
        if ($code !== '' && $id !== '') {
            $ids[$code] = $id;
        }
        foreach ($response as $value) {
            if (is_array($value)) {
                $ids += $this->indexEntityIds($value);
            }
        }

        return $ids;
    }

    /** @param array<string, mixed> $payload */
    public function updateTree(string $treeId, array $payload): void
    {
        $this->client->put('trees/' . rawurlencode($treeId), $payload);
    }

    /** @param array<string, string> $name */
    public function createTree(string $code, array $name): void
    {
        $this->client->post('trees', ['code' => $code, 'name' => $name]);
        $this->identityCache->remove($code);
    }

    /** @param array<string|int, mixed> $response */
    private function findEntityId(array $response, string $code): ?string
    {
        if ((string)($response['code'] ?? '') === $code && trim((string)($response['id'] ?? '')) !== '') {
            return (string)$response['id'];
        }
        foreach ($response as $value) {
            if (!is_array($value)) {
                continue;
            }
            $id = $this->findEntityId($value, $code);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }
}
