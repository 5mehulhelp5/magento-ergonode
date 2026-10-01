<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Model;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\Attribute\Api\MagentoAttributeTypeResolverInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean as BooleanSource;

class MagentoAttributeTypeResolver implements MagentoAttributeTypeResolverInterface
{
    private const string BOOLEAN_SOURCE_MODEL = BooleanSource::class;

    private const array ERGONODE_TYPES = [
        'boolean' => ErgonodeAttributeTypeInterface::TYPE_SELECT,
        ErgonodeAttributeTypeInterface::TYPE_DATE => ErgonodeAttributeTypeInterface::TYPE_DATE,
        'decimal' => ErgonodeAttributeTypeInterface::TYPE_NUMERIC,
        ErgonodeAttributeTypeInterface::TYPE_FILE => ErgonodeAttributeTypeInterface::TYPE_FILE,
        ErgonodeAttributeTypeInterface::TYPE_GALLERY => ErgonodeAttributeTypeInterface::TYPE_GALLERY,
        ErgonodeAttributeTypeInterface::TYPE_IMAGE => ErgonodeAttributeTypeInterface::TYPE_IMAGE,
        'multiselect' => ErgonodeAttributeTypeInterface::TYPE_MULTI_SELECT,
        ErgonodeAttributeTypeInterface::TYPE_NUMERIC => ErgonodeAttributeTypeInterface::TYPE_NUMERIC,
        ErgonodeAttributeTypeInterface::TYPE_PRICE => ErgonodeAttributeTypeInterface::TYPE_PRICE,
        ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION => ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION,
        ErgonodeAttributeTypeInterface::TYPE_SELECT => ErgonodeAttributeTypeInterface::TYPE_SELECT,
        ErgonodeAttributeTypeInterface::TYPE_TEXT => ErgonodeAttributeTypeInterface::TYPE_TEXT,
        ErgonodeAttributeTypeInterface::TYPE_TEXTAREA => ErgonodeAttributeTypeInterface::TYPE_TEXTAREA,
        ErgonodeAttributeTypeInterface::TYPE_UNIT => ErgonodeAttributeTypeInterface::TYPE_UNIT,
    ];

    public function fromStorage(string $frontendInput, string $backendType, string $sourceModel): string
    {
        $frontendInput = strtolower(trim($frontendInput));
        $backendType = strtolower(trim($backendType));
        $sourceModel = ltrim(trim($sourceModel), '\\');
        if (strcasecmp($sourceModel, self::BOOLEAN_SOURCE_MODEL) === 0) {
            return 'boolean';
        }
        if (in_array($frontendInput, [
            'select', 'multiselect', 'textarea', 'boolean', 'date', 'file', 'price', 'unit',
        ], true)) {
            return $frontendInput;
        }
        if ($frontendInput === 'media_image') {
            return 'image';
        }
        if (in_array($backendType, ['decimal', 'int'], true)) {
            return 'decimal';
        }

        return 'text';
    }

    public function toErgonodeType(string $type): ?string
    {
        return self::ERGONODE_TYPES[strtolower(trim($type))] ?? null;
    }
}
