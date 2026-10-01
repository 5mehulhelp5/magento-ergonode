<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Api;

interface AttributeValueNormalizerInterface
{
    /**
     * File and gallery translations contain lists of paths; image translations contain one path.
     *
     * @param string $type
     * @param mixed $items
     * @return array<string, float|int|string|string[]>
     */
    public function valueTranslations(string $type, mixed $items): array;
}
