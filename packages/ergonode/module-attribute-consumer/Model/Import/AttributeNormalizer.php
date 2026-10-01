<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Ergonode\Attribute\Api\AttributeDataNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class AttributeNormalizer
{
    public function __construct(
        private readonly Json $json,
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly AttributeDataNormalizerInterface $attributeDataNormalizer
    ) {
    }

    /**
     * @param array<string, mixed> $node
     * @return array{
     *     code: string,
     *     type: string,
     *     scope: string,
     *     labels: array<string, string>,
     *     parameters: array<string, bool|string>,
     *     hash: string
     * }
     * @throws LocalizedException
     */
    public function normalizeAttribute(array $node): array
    {
        $labels = $this->normalizeLabels($node['name'] ?? []);
        $runtimeType = trim((string)($node['__typename'] ?? ''));
        $canonicalType = $this->typeResolver->fromDefinitionTypeName($runtimeType);
        if ($canonicalType === null) {
            throw new LocalizedException(__('Unsupported Ergonode attribute runtime type "%1".', $runtimeType));
        }
        $normalized = [
            'code' => (string)($node['code'] ?? ''),
            'type' => $this->typeResolver->toConsumerType($canonicalType),
            'scope' => strtolower((string)($node['scope'] ?? 'global')),
            'labels' => $labels,
            'parameters' => $this->attributeDataNormalizer->parameters($canonicalType, $node),
        ];

        return $normalized + ['hash' => $this->hash($normalized)];
    }

    /**
     * @param array<string, mixed> $node
     * @return array{
     *     code: string,
     *     labels: array<string, string>,
     *     sort_order: int,
     *     hash: string
     * }
     */
    public function normalizeOption(array $node, int $sortOrder = 0): array
    {
        $normalized = [
            'code' => (string)($node['code'] ?? ''),
            'labels' => $this->normalizeLabels($node['name'] ?? []),
            'sort_order' => max(0, $sortOrder),
        ];

        return $normalized + ['hash' => $this->hash($normalized)];
    }

    /**
     * @param mixed $labels
     * @return array<string, string>
     */
    public function normalizeLabels(mixed $labels): array
    {
        return $this->attributeDataNormalizer->translations($labels);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', $this->json->serialize($payload));
    }
}
