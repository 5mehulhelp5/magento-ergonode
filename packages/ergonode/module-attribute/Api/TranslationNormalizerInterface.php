<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Api;

interface TranslationNormalizerInterface
{
    /**
     * @param mixed $items
     * @return array<string, string>
     */
    public function translations(mixed $items): array;
}
