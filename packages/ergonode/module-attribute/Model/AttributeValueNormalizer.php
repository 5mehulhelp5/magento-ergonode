<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Model;

use Ergonode\Attribute\Api\AttributeValueNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\Attribute\Api\TranslationNormalizerInterface;

class AttributeValueNormalizer implements AttributeValueNormalizerInterface, TranslationNormalizerInterface
{
    public function translations(mixed $items): array
    {
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $language = trim((string)($item['language'] ?? ''));
            if ($language !== '') {
                $result[$language] = (string)($item['value'] ?? '');
            }
        }

        return $result;
    }

    public function valueTranslations(string $type, mixed $items): array
    {
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $language = trim((string)($item['language'] ?? ''));
            $value = $type === ErgonodeAttributeTypeInterface::TYPE_TEXTAREA
                ? ($item['rawValue'] ?? null)
                : ($item['value'] ?? null);
            if ($language === '' || $value === null) {
                continue;
            }
            if (in_array($type, [
                ErgonodeAttributeTypeInterface::TYPE_FILE,
                ErgonodeAttributeTypeInterface::TYPE_GALLERY,
            ], true)) {
                $value = $this->referenceValues($value, 'path');
            } elseif ($type === ErgonodeAttributeTypeInterface::TYPE_IMAGE) {
                $value = is_array($value) ? trim((string)($value['path'] ?? '')) : '';
            } elseif (in_array($type, [
                ErgonodeAttributeTypeInterface::TYPE_MULTI_SELECT,
                ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION,
            ], true)) {
                $field = $type === ErgonodeAttributeTypeInterface::TYPE_MULTI_SELECT ? 'code' : 'sku';
                $value = $this->referenceValues($value, $field);
            } elseif ($type === ErgonodeAttributeTypeInterface::TYPE_SELECT) {
                $value = is_array($value) ? trim((string)($value['code'] ?? '')) : '';
            }
            $result[$language] = $value;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function referenceValues(mixed $items, string $field): array
    {
        $values = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $value = is_array($item) ? trim((string)($item[$field] ?? '')) : '';
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }
}
