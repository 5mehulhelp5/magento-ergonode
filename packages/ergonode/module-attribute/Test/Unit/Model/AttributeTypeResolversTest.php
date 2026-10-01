<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Test\Unit\Model;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\Attribute\Model\MagentoAttributeTypeResolver;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean as BooleanSource;
use PHPUnit\Framework\TestCase;

class AttributeTypeResolversTest extends TestCase
{
    public function testCentralTypeMetadataCoversEverySupportedType(): void
    {
        $expectedTypes = ErgonodeAttributeTypeInterface::TYPES;
        sort($expectedTypes);

        $typeCollections = [
            array_values(ErgonodeAttributeTypeInterface::DEFINITION_TYPE_NAMES),
            array_values(ErgonodeAttributeTypeInterface::VALUE_TYPE_NAMES),
            array_keys(ErgonodeAttributeTypeInterface::MUTATION_SUFFIXES),
        ];
        foreach ($typeCollections as $types) {
            $types = array_values(array_unique($types));
            sort($types);
            self::assertSame($expectedTypes, $types);
        }

        self::assertSame(
            array_keys(ErgonodeAttributeTypeInterface::VALUE_TYPE_NAMES),
            array_keys(ErgonodeAttributeTypeInterface::TRANSLATION_KEYS)
        );
        self::assertCount(
            count(ErgonodeAttributeTypeInterface::MUTATION_SUFFIXES),
            array_unique(ErgonodeAttributeTypeInterface::MUTATION_SUFFIXES)
        );
        self::assertCount(
            count(ErgonodeAttributeTypeInterface::TRANSLATION_KEYS),
            array_unique(ErgonodeAttributeTypeInterface::TRANSLATION_KEYS)
        );
    }

    public function testUsesOneCanonicalErgonodeVocabulary(): void
    {
        $resolver = new ErgonodeAttributeTypeResolver();

        self::assertSame('multi_select', $resolver->fromDefinitionTypeName('MultiSelectAttribute'));
        self::assertSame('product_relation', $resolver->fromValueTypeName('ProductRelationAttributeValue'));
        self::assertSame('multi_select', $resolver->fromConsumerType('multiselect'));
        self::assertSame('multiselect', $resolver->toConsumerType('multi_select'));
        self::assertSame('relation', $resolver->toConsumerType('product_relation'));
    }

    public function testRejectsUnsupportedBooleanAttributeDefinitionType(): void
    {
        self::assertNull((new ErgonodeAttributeTypeResolver())->fromDefinitionTypeName('BooleanAttribute'));
    }

    public function testDoesNotConvertMissingCanonicalTypeToText(): void
    {
        self::assertSame('', (new ErgonodeAttributeTypeResolver())->toConsumerType(''));
    }

    public function testNormalizesMagentoStorageAndPublisherTypes(): void
    {
        $resolver = new MagentoAttributeTypeResolver();

        self::assertSame('image', $resolver->fromStorage('media_image', 'varchar', ''));
        self::assertSame('decimal', $resolver->fromStorage('text', 'decimal', ''));
        self::assertSame('multi_select', $resolver->toErgonodeType('multiselect'));
        self::assertSame('select', $resolver->toErgonodeType('boolean'));
    }

    public function testDistinguishesBooleanSourceFromProductStatusSelect(): void
    {
        $resolver = new MagentoAttributeTypeResolver();

        self::assertSame('boolean', $resolver->fromStorage(
            'select',
            'int',
            BooleanSource::class
        ));
        self::assertSame('boolean', $resolver->fromStorage(
            'select',
            'int',
            '\\' . BooleanSource::class
        ));
        self::assertSame('select', $resolver->fromStorage(
            'select',
            'int',
            Status::class
        ));
    }
}
