<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Config;

use Ergonode\ProductMedia\Api\ImageRulesNormalizerInterface;
use Ergonode\ProductMedia\Exception\InvalidMediaConfigurationException;

class ImageRulesNormalizer implements ImageRulesNormalizerInterface
{
    public function normalize(array $rows): array
    {
        unset($rows['__empty']);
        $codes = [];
        $positions = [];
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['attribute'] ?? null)) {
                throw new InvalidMediaConfigurationException(__('Choose an Image attribute and a position between 2 and 65535.'));
            }
            $code = trim($row['attribute']);
            $position = $row['position'] ?? null;
            if ($code === '' || !$this->isValidPosition($position)) {
                throw new InvalidMediaConfigurationException(__('Choose an Image attribute and a position between 2 and 65535.'));
            }
            if (isset($codes[$code]) || isset($positions[(int)$position])) {
                throw new InvalidMediaConfigurationException(__('Each Image attribute and gallery position can be used only once.'));
            }
            $codes[$code] = true;
            $positions[(int)$position] = true;
            $result[] = ['attribute' => $code, 'position' => (int)$position];
        }
        return $result;
    }
    public function normalizePosition(mixed $position): int
    {
        if (!$this->isValidPosition($position)) {
            throw new InvalidMediaConfigurationException(__('Choose an image position between 2 and 65535.'));
        }
        return (int)$position;
    }

    private function isValidPosition(mixed $position): bool
    {
        return (is_string($position) || is_int($position)) && ctype_digit((string)$position)
            && (int)$position >= 2 && (int)$position <= 65535;
    }

}
