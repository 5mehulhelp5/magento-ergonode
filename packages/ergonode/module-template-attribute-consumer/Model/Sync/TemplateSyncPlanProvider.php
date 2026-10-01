<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\ProductAttribute\Api\ProductAttributeCodeMappingProviderInterface;
use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;

class TemplateSyncPlanProvider
{
    public function __construct(
        private readonly AttributeSetManager $attributeSetManager,
        private readonly TemplateStructureResource $resource,
        private readonly ProductAttributeCodeMappingProviderInterface $attributeMappingProvider,
        private readonly MappedTemplateAttributeResolver $mappedAttributeResolver,
        private readonly ProductAttributePlacementPolicyInterface $productAttributePolicy
    ) {
    }

    /**
     * @param array<int, array{
     *     code: string,
     *     attributes: array<int, array{code: string, sort_order: int}>
     * }> $sections
     * @return array{
     *     magento_attribute_ids: array<string, int>,
     *     sections: array<int, array{
     *         section: array<string, mixed>,
     *         mapped: array<int, array{
     *             ergonode_code: string,
     *             magento_code: string,
     *             attribute_id: int,
     *             sort_order: int
     *         }>,
     *         skipped: array<int, array{
     *             ergonode_code: string,
     *             magento_code: string|null,
     *             reason: string,
     *             sort_order: int
     *         }>,
     *         duplicates: array<int, array{
     *             ergonode_code: string,
     *             magento_code: string,
     *             attribute_id: int,
     *             sort_order: int
     *         }>
     *     }>
     * }
     */
    public function create(array $sections): array
    {
        $attributeCodes = $this->collectErgonodeAttributeCodes($sections);
        $mappingByErgonodeCode = $this->attributeMappingProvider->getMagentoAttributeCodes($attributeCodes);
        $magentoAttributes = $this->resource->loadMagentoAttributes(
            $this->attributeSetManager->getProductEntityTypeId(),
            array_values($mappingByErgonodeCode)
        );
        $magentoAttributeIds = [];
        $systemAttributeCodes = [];
        foreach ($magentoAttributes as $magentoCode => $attribute) {
            if (!$attribute['is_user_defined']
                || $this->productAttributePolicy->isProtected($magentoCode)
            ) {
                $systemAttributeCodes[$magentoCode] = true;
                continue;
            }
            $magentoAttributeIds[$magentoCode] = $attribute['attribute_id'];
        }
        $claimedAttributeIds = [];
        $plannedSections = [];

        foreach ($sections as $section) {
            $resolved = $this->mappedAttributeResolver->resolve(
                $section['attributes'],
                $mappingByErgonodeCode,
                $magentoAttributeIds,
                $systemAttributeCodes
            );
            $mapped = [];
            $duplicates = [];

            foreach ($resolved['mapped'] as $attribute) {
                $attributeId = $attribute['attribute_id'];
                if (isset($claimedAttributeIds[$attributeId])) {
                    $duplicates[] = $attribute;
                    continue;
                }

                $claimedAttributeIds[$attributeId] = true;
                $mapped[] = $attribute;
            }

            $plannedSections[] = [
                'section' => $section,
                'mapped' => $mapped,
                'skipped' => $resolved['skipped'],
                'duplicates' => $duplicates,
            ];
        }

        return [
            'magento_attribute_ids' => $magentoAttributeIds,
            'sections' => $plannedSections,
        ];
    }

    /**
     * @param array<int, array{attributes: array<int, array{code: string}>}> $sections
     * @return string[]
     */
    private function collectErgonodeAttributeCodes(array $sections): array
    {
        $codes = [];
        foreach ($sections as $section) {
            foreach ($section['attributes'] as $attribute) {
                $code = trim((string)$attribute['code']);
                if ($code !== '') {
                    $codes[$code] = true;
                }
            }
        }

        return array_keys($codes);
    }
}
