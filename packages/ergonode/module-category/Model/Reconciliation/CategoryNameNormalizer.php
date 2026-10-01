<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Reconciliation;

use Normalizer;

use function mb_strtolower;
use function preg_replace;
use function trim;

class CategoryNameNormalizer
{
    public function normalize(string $name): string
    {
        $name = Normalizer::normalize($name, Normalizer::FORM_C) ?: $name;
        $name = preg_replace('/[\p{Z}\s]+/u', ' ', $name) ?? $name;

        return mb_strtolower(trim($name), 'UTF-8');
    }
}
