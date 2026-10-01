<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;

class AttributeTypeCompatibility implements AttributeTypeCompatibilityInterface
{
    private const array ATTRIBUTE_COMPATIBILITY_MAP = [
        'boolean' => ['boolean'],
        'date' => ['date'],
        'decimal' => ['decimal'],
        'file' => ['file', 'text', 'textarea'],
        'image' => ['image', 'file', 'text', 'textarea'],
        'multiselect' => ['multiselect', 'text', 'textarea'],
        'numeric' => ['decimal'],
        'price' => ['price', 'decimal', 'text', 'textarea'],
        'select' => ['select', 'text', 'textarea', 'multiselect', 'boolean'],
        'text' => ['text', 'textarea', 'select', 'multiselect'],
        'textarea' => ['textarea', 'text', 'select', 'multiselect'],
        'unit' => ['unit', 'decimal', 'text', 'textarea'],
    ];

    public function canMapAttributes(string $ergonodeType, string $magentoType): bool
    {
        $ergonodeType = $this->normalize($ergonodeType);
        $magentoType = $this->normalize($magentoType);

        if ($ergonodeType === '' || $magentoType === '') {
            return false;
        }

        return in_array($magentoType, self::ATTRIBUTE_COMPATIBILITY_MAP[$ergonodeType] ?? [], true);
    }

    public function canMapOptions(string $ergonodeType, string $magentoType): bool
    {
        $ergonodeType = $this->normalize($ergonodeType);
        $magentoType = $this->normalize($magentoType);

        if ($ergonodeType === 'select') {
            return in_array($magentoType, ['select', 'multiselect', 'boolean'], true);
        }

        return $ergonodeType === 'multiselect' && $magentoType === 'multiselect';
    }

    public function getAttributeCompatibilityMap(): array
    {
        return self::ATTRIBUTE_COMPATIBILITY_MAP;
    }

    private function normalize(string $type): string
    {
        return strtolower(trim($type));
    }
}
