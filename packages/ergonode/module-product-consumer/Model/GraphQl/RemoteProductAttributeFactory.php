<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\GraphQl;

use Ergonode\Attribute\Api\AttributeValueNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedNumberValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;

class RemoteProductAttributeFactory
{
    public function __construct(private readonly AttributeValueNormalizerInterface $valueNormalizer)
    {
    }

    /** @throws LocalizedException */
    public function fromPayload(string $code, string $type, mixed $translations): RemoteProductAttribute
    {
        try {
            $attributeType = new RemoteProductAttributeType($type);
            $values = $type === ErgonodeAttributeTypeInterface::TYPE_FILE
                ? $this->fileValues($translations)
                : $this->valueNormalizer->valueTranslations($type, $translations);

            return new RemoteProductAttribute($code, $attributeType, match ($attributeType->valueShape) {
                RemoteProductAttributeType::VALUE_SHAPE_STRING => $this->stringValues($values),
                RemoteProductAttributeType::VALUE_SHAPE_NUMBER => $this->numberValues($values),
                RemoteProductAttributeType::VALUE_SHAPE_STRING_LIST => $this->stringListValues($values),
            });
        } catch (InvalidArgumentException $exception) {
            throw new LocalizedException(
                __('Ergonode returned an invalid value for product attribute "%1".', $code),
                $exception
            );
        }
    }

    /** @param array<string, float|int|string|string[]> $values */
    private function stringValues(array $values): LocalizedStringValues
    {
        $strings = [];
        foreach ($values as $language => $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Expected localized string values.');
            }
            $strings[$language] = $value;
        }

        return new LocalizedStringValues($strings);
    }

    /** @param array<string, float|int|string|string[]> $values */
    private function numberValues(array $values): LocalizedNumberValues
    {
        $numbers = [];
        foreach ($values as $language => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Expected localized numeric values.');
            }
            $numbers[$language] = $value;
        }

        return new LocalizedNumberValues($numbers);
    }

    /** @param array<string, float|int|string|string[]> $values */
    private function stringListValues(array $values): LocalizedStringListValues
    {
        $lists = [];
        foreach ($values as $language => $value) {
            if (!is_array($value) || !array_is_list($value)) {
                throw new InvalidArgumentException('Expected localized string-list values.');
            }
            foreach ($value as $item) {
                if (!is_string($item)) {
                    throw new InvalidArgumentException('Expected localized string-list values.');
                }
            }
            $lists[$language] = $value;
        }

        return new LocalizedStringListValues($lists);
    }

    /** @return array<string, string> */
    private function fileValues(mixed $translations): array
    {
        $values = [];
        foreach (is_array($translations) ? $translations : [] as $translation) {
            if (!is_array($translation)) {
                continue;
            }
            $language = trim((string)($translation['language'] ?? ''));
            $value = $translation['value'] ?? null;
            if (is_array($value) && array_is_list($value)) {
                $value = reset($value) ?: null;
            }
            $path = is_array($value) ? trim((string)($value['path'] ?? '')) : '';
            if ($language !== '' && $path !== '') {
                $values[$language] = $path;
            }
        }

        return $values;
    }
}
