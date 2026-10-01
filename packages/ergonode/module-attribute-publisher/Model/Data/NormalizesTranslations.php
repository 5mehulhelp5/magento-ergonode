<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Data;

use InvalidArgumentException;

trait NormalizesTranslations
{
    /** @param array<string, string> $values @return array<string, string> */
    private function normalizeTranslations(array $values): array
    {
        $normalized = [];
        foreach ($values as $language => $value) {
            if (!is_string($language) || trim($language) === '' || !is_string($value)) {
                throw new InvalidArgumentException('Translations require non-empty language codes and string values.');
            }
            $normalized[trim($language)] = $value;
        }
        ksort($normalized);

        return $normalized;
    }
}
