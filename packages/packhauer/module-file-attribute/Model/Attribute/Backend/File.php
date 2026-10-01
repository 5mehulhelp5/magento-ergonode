<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Model\Attribute\Backend;

use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use PackHauer\FileAttribute\Api\FileStorageInterface;

class File extends AbstractBackend
{
    public function __construct(
        private readonly FileStorageInterface $fileStorage
    ) {
    }

    public function beforeSave($object): static
    {
        parent::beforeSave($object);
        $attributeCode = (string)$this->getAttribute()->getAttributeCode();
        if (!$object instanceof DataObject || !$object->hasData($attributeCode)) {
            return $this;
        }

        $object->setData($attributeCode, $this->normalize($object->getData($attributeCode), true));

        return $this;
    }

    public function validate($object): bool
    {
        parent::validate($object);
        $this->normalize($object->getData($this->getAttribute()->getAttributeCode()), false);

        return true;
    }

    /** @throws LocalizedException */
    private function normalize(mixed $value, bool $promoteTemporaryFile): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (is_string($value)) {
            return $this->fileStorage->normalizePath($value);
        }
        if (!is_array($value)) {
            throw new LocalizedException(__('File attribute value must be a media path or an uploaded file.'));
        }

        $files = array_is_list($value) ? $value : [$value];
        if (count($files) !== 1 || !is_array($files[0])) {
            throw new LocalizedException(__('File attributes accept exactly one file.'));
        }

        $file = $files[0];
        $path = $this->fileStorage->normalizePath((string)($file['path'] ?? $file['file'] ?? ''));
        if ($promoteTemporaryFile && !empty($file['tmp_name'])) {
            return $this->fileStorage->promoteTemporaryFile(
                $path,
                (string)$this->getAttribute()->getAttributeCode()
            );
        }

        return $path;
    }
}
