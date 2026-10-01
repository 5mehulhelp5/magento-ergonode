<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Api;

interface ErgonodeAttributeTypeInterface
{
    public const string TYPE_DATE = 'date';
    public const string TYPE_FILE = 'file';
    public const string TYPE_GALLERY = 'gallery';
    public const string TYPE_IMAGE = 'image';
    public const string TYPE_MULTI_SELECT = 'multi_select';
    public const string TYPE_NUMERIC = 'numeric';
    public const string TYPE_PRICE = 'price';
    public const string TYPE_PRODUCT_RELATION = 'product_relation';
    public const string TYPE_SELECT = 'select';
    public const string TYPE_TEXT = 'text';
    public const string TYPE_TEXTAREA = 'textarea';
    public const string TYPE_UNIT = 'unit';

    public const array STRING_TYPES = [
        self::TYPE_DATE,
        self::TYPE_IMAGE,
        self::TYPE_SELECT,
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
    ];

    public const array LIST_TYPES = [
        self::TYPE_FILE,
        self::TYPE_GALLERY,
        self::TYPE_MULTI_SELECT,
        self::TYPE_PRODUCT_RELATION,
    ];

    public const array NUMERIC_TYPES = [
        self::TYPE_NUMERIC,
        self::TYPE_PRICE,
        self::TYPE_UNIT,
    ];

    public const array MEDIA_TYPES = [
        self::TYPE_FILE,
        self::TYPE_GALLERY,
        self::TYPE_IMAGE,
    ];

    public const array TYPES = [
        ...self::STRING_TYPES,
        ...self::LIST_TYPES,
        ...self::NUMERIC_TYPES,
    ];

    public const array DEFINITION_TYPE_NAMES = [
        'DateAttribute' => self::TYPE_DATE,
        'FileAttribute' => self::TYPE_FILE,
        'GalleryAttribute' => self::TYPE_GALLERY,
        'ImageAttribute' => self::TYPE_IMAGE,
        'MultiSelectAttribute' => self::TYPE_MULTI_SELECT,
        'NumericAttribute' => self::TYPE_NUMERIC,
        'PriceAttribute' => self::TYPE_PRICE,
        'ProductRelationAttribute' => self::TYPE_PRODUCT_RELATION,
        'SelectAttribute' => self::TYPE_SELECT,
        'TextAttribute' => self::TYPE_TEXT,
        'TextareaAttribute' => self::TYPE_TEXTAREA,
        'UnitAttribute' => self::TYPE_UNIT,
    ];

    public const array VALUE_TYPE_NAMES = [
        'DateAttributeValue' => self::TYPE_DATE,
        'FileAttributeValue' => self::TYPE_FILE,
        'GalleryAttributeValue' => self::TYPE_GALLERY,
        'ImageAttributeValue' => self::TYPE_IMAGE,
        'MultiSelectAttributeValue' => self::TYPE_MULTI_SELECT,
        'NumberAttributeValue' => self::TYPE_NUMERIC,
        'PriceAttributeValue' => self::TYPE_PRICE,
        'ProductRelationAttributeValue' => self::TYPE_PRODUCT_RELATION,
        'SelectAttributeValue' => self::TYPE_SELECT,
        'TextAttributeValue' => self::TYPE_TEXT,
        'TextareaAttributeValue' => self::TYPE_TEXTAREA,
        'UnitAttributeValue' => self::TYPE_UNIT,
    ];

    public const array MUTATION_SUFFIXES = [
        self::TYPE_DATE => 'Date',
        self::TYPE_FILE => 'File',
        self::TYPE_GALLERY => 'Gallery',
        self::TYPE_IMAGE => 'Image',
        self::TYPE_MULTI_SELECT => 'MultiSelect',
        self::TYPE_NUMERIC => 'Numeric',
        self::TYPE_PRICE => 'Price',
        self::TYPE_PRODUCT_RELATION => 'ProductRelation',
        self::TYPE_SELECT => 'Select',
        self::TYPE_TEXT => 'Text',
        self::TYPE_TEXTAREA => 'Textarea',
        self::TYPE_UNIT => 'Unit',
    ];

    public const array TRANSLATION_KEYS = [
        'DateAttributeValue' => 'dateAttributeValueTranslations',
        'FileAttributeValue' => 'fileAttributeValueTranslations',
        'GalleryAttributeValue' => 'galleryAttributeValueTranslations',
        'ImageAttributeValue' => 'imageAttributeValueTranslations',
        'MultiSelectAttributeValue' => 'multiSelectAttributeValueTranslations',
        'NumberAttributeValue' => 'numericAttributeValueTranslations',
        'PriceAttributeValue' => 'priceAttributeValueTranslations',
        'ProductRelationAttributeValue' => 'productRelationAttributeValueTranslations',
        'SelectAttributeValue' => 'selectAttributeValueTranslations',
        'TextAttributeValue' => 'textAttributeValueTranslations',
        'TextareaAttributeValue' => 'textareaAttributeValueTranslations',
        'UnitAttributeValue' => 'unitAttributeValueTranslations',
    ];
}
