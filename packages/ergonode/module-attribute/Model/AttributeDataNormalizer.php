<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Model;

use Ergonode\Attribute\Api\AttributeDataNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;

class AttributeDataNormalizer implements AttributeDataNormalizerInterface
{
    public function translations(mixed $items): array
    {
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $language = trim((string)($item['language'] ?? ''));
            $value = (string)($item['value'] ?? '');
            if ($language !== '' && $value !== '') {
                $result[$language] = $value;
            }
        }
        ksort($result);

        return $result;
    }

    public function parameters(string $type, array $node): array
    {
        return match ($type) {
            ErgonodeAttributeTypeInterface::TYPE_DATE => ['format' => trim((string)($node['format'] ?? ''))],
            ErgonodeAttributeTypeInterface::TYPE_NUMERIC,
            ErgonodeAttributeTypeInterface::TYPE_TEXT => ['unique' => (bool)($node['unique'] ?? false)],
            ErgonodeAttributeTypeInterface::TYPE_PRICE => [
                'currency' => trim((string)($node['currency'] ?? '')),
            ],
            ErgonodeAttributeTypeInterface::TYPE_TEXTAREA => [
                'richEdit' => (bool)($node['richEdit'] ?? false),
            ],
            ErgonodeAttributeTypeInterface::TYPE_UNIT => isset($node['unit']) && is_array($node['unit']) ? [
                'unitName' => trim((string)($node['unit']['name'] ?? '')),
                'unitSymbol' => trim((string)($node['unit']['symbol'] ?? '')),
            ] : [],
            default => [],
        };
    }
}
