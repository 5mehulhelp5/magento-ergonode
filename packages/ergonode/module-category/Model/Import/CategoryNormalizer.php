<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Magento\Framework\Serialize\Serializer\Json;

class CategoryNormalizer
{
    public function __construct(
        private readonly Json $json
    ) {
    }

    /**
     * @param array<string, mixed> $node
     * @return array{
     *     code: string,
     *     parent_code: string|null,
     *     labels: array<string, string>,
     *     sort_order: int,
     *     raw: array<string, mixed>,
     *     hash: string
     * }|null
     */
    public function normalizeTreeNode(array $node, int $sortOrder): ?array
    {
        $category = isset($node['category']) && is_array($node['category']) ? $node['category'] : [];
        $parent = isset($node['parentCategory']) && is_array($node['parentCategory']) ? $node['parentCategory'] : [];
        $code = trim((string)($category['code'] ?? ''));

        if ($code === '') {
            return null;
        }

        $normalized = [
            'code' => $code,
            'parent_code' => $this->normalizeNullableCode($parent['code'] ?? null),
            'labels' => $this->normalizeLabels($category['name'] ?? []),
            'sort_order' => max(0, $sortOrder),
        ];

        return $normalized + [
            'raw' => $node,
            'hash' => $this->hash($normalized),
        ];
    }

    /** @return array<string, string> */
    private function normalizeLabels(mixed $labels): array
    {
        $result = [];

        if (!is_array($labels)) {
            return $result;
        }

        foreach ($labels as $label) {
            if (!is_array($label)) {
                continue;
            }

            $language = trim((string)($label['language'] ?? ''));
            $value = trim((string)($label['value'] ?? ''));
            if ($language !== '' && $value !== '') {
                $result[$language] = $value;
            }
        }

        ksort($result);

        return $result;
    }

    private function normalizeNullableCode(mixed $code): ?string
    {
        $code = trim((string)$code);

        return $code !== '' ? $code : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', $this->json->serialize($payload));
    }
}
