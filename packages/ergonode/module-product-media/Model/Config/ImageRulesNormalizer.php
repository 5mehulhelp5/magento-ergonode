<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Config;

use Ergonode\ProductMedia\Api\ImageRulesNormalizerInterface;
use Magento\Framework\Exception\LocalizedException;

class ImageRulesNormalizer implements ImageRulesNormalizerInterface
{
    public function normalize(array $rows): array
    {
        unset($rows['__empty']);
        $codes = [];
        $positions = [];
        $result = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['attribute'] ?? ''));
            $position = (string)($row['position'] ?? '');
            if ($code === '' || !ctype_digit($position) || (int)$position < 2 || (int)$position > 65535) {
                throw new LocalizedException(__('Choose an Image attribute and a position between 2 and 65535.'));
            }
            if (isset($codes[$code]) || isset($positions[(int)$position])) {
                throw new LocalizedException(__('Each Image attribute and gallery position can be used only once.'));
            }
            $codes[$code] = true;
            $positions[(int)$position] = true;
            $result[] = ['attribute' => $code, 'position' => (int)$position];
        }
        return $result;
    }
}
