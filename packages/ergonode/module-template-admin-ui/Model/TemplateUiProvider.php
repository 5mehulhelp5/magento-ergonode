<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Model;

use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Ergonode\Template\Api\TemplateSnapshotProviderInterface;

class TemplateUiProvider
{
    public function __construct(
        private readonly TemplateSnapshotProviderInterface $snapshotProvider,
        private readonly ProductAttributeSetProviderInterface $attributeSetProvider,
        private readonly TemplateNameResolver $templateNameResolver
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        $rows = $this->snapshotProvider->getTemplates();

        return array_map(
            function (array $row): array {
                $isMapped = $row['attribute_set_id'] !== null
                    && ($row['attribute_set_name'] ?? null) !== null;
                $isDeleted = (bool)$row['is_deleted'];
                $code = (string)$row['code'];
                $rawJson = (string)($row['raw_json'] ?? '');

                return [
                    'entity_id' => (int)$row['entity_id'],
                    'code' => $code,
                    'name' => $this->templateNameResolver->resolve(
                        $rawJson,
                        $code
                    ),
                    'names' => $this->templateNameResolver->resolveAll($rawJson),
                    'attribute_set_id' => $isMapped ? (int)$row['attribute_set_id'] : null,
                    'attribute_set_name' => (string)($row['attribute_set_name'] ?? ''),
                    'is_deleted' => $isDeleted,
                    'synced_at' => (string)$row['synced_at'],
                    'updated_at' => (string)$row['updated_at'],
                    'status' => $isDeleted ? 'Usunięty w Ergonode' : ($isMapped ? 'Zmapowany' : 'Brak attribute set'),
                    'status_tone' => $isDeleted ? 'warning' : ($isMapped ? 'ok' : 'warning'),
                ];
            },
            $rows
        );
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getAttributeSets(): array
    {
        return $this->attributeSetProvider->getProductAttributeSets();
    }
}
