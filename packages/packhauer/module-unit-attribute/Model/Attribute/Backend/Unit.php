<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Model\Attribute\Backend;

use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class Unit extends AbstractBackend
{
    public function beforeSave($object): static
    {
        parent::beforeSave($object);
        $attributeCode = (string)$this->getAttribute()->getAttributeCode();
        if (!$object instanceof DataObject || !$object->hasData($attributeCode)) {
            return $this;
        }

        $value = $object->getData($attributeCode);
        if ($value === null || $value === '') {
            return $this;
        }

        $object->setData($attributeCode, $this->normalize($value));

        return $this;
    }

    public function validate($object): bool
    {
        parent::validate($object);
        $value = $object->getData($this->getAttribute()->getAttributeCode());
        if ($value === null || $value === '') {
            return true;
        }

        $this->normalize($value);

        return true;
    }

    /** @throws LocalizedException */
    private function normalize(mixed $value): string
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new LocalizedException(__('Unit attribute value must be a number.'));
        }

        $normalized = str_replace(',', '.', trim((string)$value));
        if ($normalized === '' || !is_numeric($normalized) || !is_finite((float)$normalized)) {
            throw new LocalizedException(__('Unit attribute value must be a finite number without a unit symbol.'));
        }

        return $normalized;
    }
}
