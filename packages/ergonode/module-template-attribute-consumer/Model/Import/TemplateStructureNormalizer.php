<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Import;

use Ergonode\Attribute\Api\AttributeDataNormalizerInterface;
use Ergonode\TemplateAttributeConsumer\Model\Template\UnassignedTemplateSection;
use Magento\Framework\Serialize\Serializer\Json;

class TemplateStructureNormalizer
{
    public function __construct(
        private readonly Json $json,
        private readonly AttributeDataNormalizerInterface $attributeDataNormalizer
    ) {
    }

    /**
     * @param array<string, mixed> $node
     * @return array{
     *     code: string,
     *     sections: array<int, array{
     *         code: string,
     *         labels: array<string, string>,
     *         is_synthetic: bool,
     *         sort_order: int,
     *         attributes: array<int, array{code: string, sort_order: int}>,
     *         raw: array<string, mixed>,
     *         hash: string
     *     }>,
     *     raw: array<string, mixed>,
     *     hash: string
     * }
     */
    public function normalize(array $node): array
    {
        $sections = [];
        $assignedAttributes = [];
        $sectionEdges = $node['sectionList']['edges'] ?? [];

        if (is_array($sectionEdges)) {
            foreach ($sectionEdges as $edge) {
                if (!is_array($edge) || !isset($edge['node']) || !is_array($edge['node'])) {
                    continue;
                }

                $section = $this->normalizeSection($edge['node'], 1000 + count($sections));
                if ($section['code'] === '') {
                    continue;
                }

                foreach ($section['attributes'] as $attribute) {
                    $assignedAttributes[$attribute['code']] = true;
                }

                $sections[] = $section;
            }
        }

        $rootAttributes = $this->normalizeAttributes($node['attributeList']['edges'] ?? []);
        $unassignedAttributes = [];
        foreach ($rootAttributes as $attribute) {
            if (!isset($assignedAttributes[$attribute['code']])) {
                $unassignedAttributes[] = [
                    'code' => $attribute['code'],
                    'sort_order' => count($unassignedAttributes) + 1,
                ];
            }
        }

        if ($unassignedAttributes) {
            array_unshift($sections, [
                'code' => UnassignedTemplateSection::CODE,
                'labels' => [],
                'is_synthetic' => true,
                'sort_order' => 1000,
                'attributes' => $unassignedAttributes,
                'raw' => UnassignedTemplateSection::RAW,
                'hash' => $this->hash([
                    'code' => UnassignedTemplateSection::CODE,
                    'labels' => [],
                    'is_synthetic' => true,
                    'sort_order' => 1000,
                    'attributes' => $unassignedAttributes,
                ]),
            ]);
        }

        foreach ($sections as $index => &$section) {
            $section['sort_order'] = 1000 + $index;
            $section['hash'] = $this->hash([
                'code' => $section['code'],
                'labels' => $section['labels'],
                'is_synthetic' => $section['is_synthetic'],
                'sort_order' => $section['sort_order'],
                'attributes' => $section['attributes'],
            ]);
        }
        unset($section);

        $hashPayload = [
            'code' => (string)($node['code'] ?? ''),
            'name' => $this->attributeDataNormalizer->translations($node['name'] ?? []),
            'sections' => array_map(
                static fn (array $section): array => [
                    'code' => $section['code'],
                    'labels' => $section['labels'],
                    'is_synthetic' => $section['is_synthetic'],
                    'sort_order' => $section['sort_order'],
                    'attributes' => $section['attributes'],
                ],
                $sections
            ),
        ];

        return [
            'code' => $hashPayload['code'],
            'sections' => $sections,
            'raw' => $node,
            'hash' => $this->hash($hashPayload),
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @return array{
     *     code: string,
     *     labels: array<string, string>,
     *     is_synthetic: bool,
     *     sort_order: int,
     *     attributes: array<int, array{code: string, sort_order: int}>,
     *     raw: array<string, mixed>,
     *     hash: string
     * }
     */
    private function normalizeSection(array $node, int $sortOrder): array
    {
        $attributes = $this->normalizeAttributes($node['attributeList']['edges'] ?? []);
        $normalized = [
            'code' => (string)($node['code'] ?? ''),
            'labels' => $this->attributeDataNormalizer->translations($node['name'] ?? []),
            'is_synthetic' => false,
            'sort_order' => $sortOrder,
            'attributes' => $attributes,
        ];

        return $normalized + [
            'raw' => $node,
            'hash' => $this->hash($normalized),
        ];
    }

    /** @return array<int, array{code: string, sort_order: int}> */
    private function normalizeAttributes(mixed $edges): array
    {
        if (!is_array($edges)) {
            return [];
        }

        $attributes = [];
        $seen = [];
        foreach ($edges as $edge) {
            if (!is_array($edge) || !isset($edge['node']) || !is_array($edge['node'])) {
                continue;
            }

            $code = (string)($edge['node']['code'] ?? '');
            if ($code === '' || isset($seen[$code])) {
                continue;
            }

            $seen[$code] = true;
            $attributes[] = [
                'code' => $code,
                'sort_order' => count($attributes) + 1,
            ];
        }

        return $attributes;
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', $this->json->serialize($payload));
    }
}
