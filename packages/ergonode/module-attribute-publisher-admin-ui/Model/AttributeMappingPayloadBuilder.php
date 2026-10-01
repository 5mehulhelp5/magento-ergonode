<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisherAdminUi\Model;

class AttributeMappingPayloadBuilder
{
    /**
     * @param array<string, mixed> $source @return array<string, mixed>
     */
    public function build(array $source): array
    {
        return [
            'label' => trim((string)($source['label'] ?? $source['code'] ?? '')),
            'code' => trim((string)($source['code'] ?? '')),
            'type' => strtolower(trim((string)($source['target_type'] ?? $source['type'] ?? ''))),
            'scope' => strtolower(trim((string)($source['scope'] ?? ''))) === 'global' ? 'global' : 'local',
            'source' => 'ergo',
            'pending_create' => true,
        ];
    }
}
