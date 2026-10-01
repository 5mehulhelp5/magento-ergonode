<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeAdminUi\Plugin;

class MappingBlockPlugin
{
    /** @param array<int, array<string, mixed>> $result @return array<int, array<string, mixed>> */
    public function afterGetMagentoAttributes(object $subject, array $result): array
    {
        unset($subject);

        return array_map($this->decorateAttribute(...), $result);
    }

    /** @param array<int, array<string, mixed>> $result @return array<int, array<string, mixed>> */
    public function afterGetDraftMappings(object $subject, array $result): array
    {
        unset($subject);

        return $this->decorateMappings($result);
    }

    /** @param array<int, array<string, mixed>> $result @return array<int, array<string, mixed>> */
    public function afterGetMappedAttributes(object $subject, array $result): array
    {
        unset($subject);

        return $this->decorateMappings($result);
    }

    /** @param array<int, array<string, mixed>> $mappings @return array<int, array<string, mixed>> */
    private function decorateMappings(array $mappings): array
    {
        foreach ($mappings as &$mapping) {
            if (isset($mapping['right']) && is_array($mapping['right'])) {
                $mapping['right'] = $this->decorateAttribute($mapping['right']);
            }
        }
        unset($mapping);

        return $mappings;
    }

    /** @param array<string, mixed> $attribute @return array<string, mixed> */
    private function decorateAttribute(array $attribute): array
    {
        if (!empty($attribute['category_reference'])) {
            $attribute['label'] = (string)__('%1 · Category reference', (string)($attribute['label'] ?? ''));
        }

        return $attribute;
    }
}
