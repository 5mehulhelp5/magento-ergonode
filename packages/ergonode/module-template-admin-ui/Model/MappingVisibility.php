<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Model;

use Ergonode\Core\Api\MappingVisibilitySaverInterface;

class MappingVisibility
{
    public function __construct(private readonly MappingVisibilitySaverInterface $visibilitySaver)
    {
    }

    /** @param array<int, array<string, mixed>> $visibility */
    public function save(array $visibility): void
    {
        $this->visibilitySaver->saveMany($this->normalizeVisibility($visibility));
    }

    /**
     * @param array<int, array<string, mixed>> $visibility
     * @return array<int, array{entity_type: string, source: string, identifier: string, active: bool}>
     */
    private function normalizeVisibility(array $visibility): array
    {
        $normalized = [];

        foreach ($visibility as $item) {
            $source = (string)($item['source'] ?? '');
            $identifier = trim((string)($item['code'] ?? $item['identifier'] ?? ''));
            if (!in_array($source, ['ergo', 'magento'], true) || $identifier === '') {
                continue;
            }

            $normalized[] = [
                'entity_type' => 'template',
                'source' => $source,
                'identifier' => $identifier,
                'active' => !empty($item['active']),
            ];
        }

        return $normalized;
    }
}
