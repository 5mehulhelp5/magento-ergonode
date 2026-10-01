<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Mapping;

use Ergonode\Core\Api\MappingVisibilitySaverInterface;

class CategoryMappingVisibility
{
    public function __construct(
        private readonly MappingVisibilitySaverInterface $visibilitySaver
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $visibility
     */
    public function save(int $categoryTreeId, array $visibility): void
    {
        $this->visibilitySaver->saveMany($this->normalize($categoryTreeId, $visibility));
    }

    /**
     * @param array<int, array<string, mixed>> $visibility
     * @return array<int, array{
     *     entity_type: string,
     *     source: string,
     *     parent_identifier: string,
     *     identifier: string,
     *     active: bool
     * }>
     */
    private function normalize(int $categoryTreeId, array $visibility): array
    {
        $result = [];

        foreach ($visibility as $item) {
            $source = trim((string)($item['source'] ?? ''));
            $identifier = trim((string)($item['identifier'] ?? $item['code'] ?? ''));
            if (!in_array($source, ['ergo', 'magento'], true) || $identifier === '') {
                continue;
            }

            $result[] = [
                'entity_type' => 'category',
                'source' => $source,
                'parent_identifier' => (string)$categoryTreeId,
                'identifier' => $identifier,
                'active' => !empty($item['active']),
            ];
        }

        return $result;
    }
}
