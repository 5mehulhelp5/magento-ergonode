<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Import;

use Ergonode\Attribute\Api\AttributeValueNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\Attribute\Api\TranslationNormalizerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class CategoryEntityNormalizer
{
    public function __construct(
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly AttributeValueNormalizerInterface $valueNormalizer,
        private readonly TranslationNormalizerInterface $translationNormalizer,
        private readonly Json $json
    ) {
    }

    /**
     * @param array<string, mixed> $category
     * @return array{code: string, labels: array<string, string>, attributes: array<int, array<string, mixed>>,
     *     hash: string, raw: array<string, mixed>}
     */
    public function normalize(array $category): array
    {
        $labels = $this->translationNormalizer->translations($category['name'] ?? []);
        $attributes = [];
        foreach ((array)($category['attributeList']['edges'] ?? []) as $edge) {
            $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
            $typeName = (string)($node['__typename'] ?? '');
            $type = $this->typeResolver->fromValueTypeName($typeName);
            $attributeCode = trim((string)($node['attribute']['code'] ?? ''));
            if ($type === null || $attributeCode === '') {
                throw new LocalizedException(__(
                    'Ergonode returned an unsupported category attribute value type "%1".',
                    $typeName
                ));
            }
            $translationKey = ErgonodeAttributeTypeInterface::TRANSLATION_KEYS[$typeName] ?? '';
            $attributes[] = [
                'code' => $attributeCode,
                'type' => $type,
                'values' => $this->valueNormalizer->valueTranslations(
                    $type,
                    $translationKey !== '' ? ($node[$translationKey] ?? []) : []
                ),
            ];
        }
        usort($attributes, static fn (array $first, array $second): int => [
            $first['code'], $first['type'],
        ] <=> [$second['code'], $second['type']]);
        ksort($labels);
        $normalized = ['code' => (string)$category['code'], 'labels' => $labels, 'attributes' => $attributes];

        return $normalized + [
            'hash' => hash('sha256', $this->json->serialize($normalized)),
            'raw' => ['code' => $category['code'], 'name' => $labels, 'attributeList' => $category['attributeList']],
        ];
    }
}
