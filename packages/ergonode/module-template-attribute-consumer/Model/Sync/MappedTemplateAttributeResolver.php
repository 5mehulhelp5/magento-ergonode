<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

class MappedTemplateAttributeResolver
{
    public const string SKIP_INCOMPLETE_MAPPING = 'incomplete_mapping';
    public const string SKIP_SYSTEM_ATTRIBUTE = 'system_attribute';

    /**
     * @param array<int, array{code: string, sort_order: int}> $attributes
     * @param array<string, string> $mappingByErgonodeCode
     * @param array<string, int> $magentoAttributeIds
     * @return array{
     *     mapped: array<int, array{
     *         ergonode_code: string,
     *         magento_code: string,
     *         attribute_id: int,
     *         sort_order: int
     *     }>,
     *     skipped: array<int, array{ergonode_code: string, magento_code: string|null, reason: string}>
     * }
     */
    public function resolve(
        array $attributes,
        array $mappingByErgonodeCode,
        array $magentoAttributeIds,
        array $systemAttributeCodes = []
    ): array {
        $mapped = [];
        $skipped = [];

        foreach ($attributes as $attribute) {
            $ergonodeCode = (string)$attribute['code'];
            $magentoCode = $mappingByErgonodeCode[$ergonodeCode] ?? null;
            if ($magentoCode !== null && isset($systemAttributeCodes[$magentoCode])) {
                $skipped[] = [
                    'ergonode_code' => $ergonodeCode,
                    'magento_code' => $magentoCode,
                    'reason' => self::SKIP_SYSTEM_ATTRIBUTE,
                ];
                continue;
            }
            if (!$magentoCode || !isset($magentoAttributeIds[$magentoCode])) {
                $skipped[] = [
                    'ergonode_code' => $ergonodeCode,
                    'magento_code' => $magentoCode,
                    'reason' => self::SKIP_INCOMPLETE_MAPPING,
                ];
                continue;
            }

            $mapped[] = [
                'ergonode_code' => $ergonodeCode,
                'magento_code' => $magentoCode,
                'attribute_id' => (int)$magentoAttributeIds[$magentoCode],
                'sort_order' => max(1, (int)$attribute['sort_order']),
            ];
        }

        return [
            'mapped' => $mapped,
            'skipped' => $skipped,
        ];
    }
}
