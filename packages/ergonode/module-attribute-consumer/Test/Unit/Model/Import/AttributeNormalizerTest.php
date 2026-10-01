<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Attribute\Model\AttributeDataNormalizer;
use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\AttributeConsumer\Model\Import\AttributeNormalizer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class AttributeNormalizerTest extends TestCase
{
    public function testPreservesExactAttributeTypesAndTheirParameters(): void
    {
        $normalizer = new AttributeNormalizer(
            new Json(),
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer()
        );

        $unit = $normalizer->normalizeAttribute([
            '__typename' => 'UnitAttribute',
            'code' => 'length',
            'unit' => ['name' => 'CENTIMETER', 'symbol' => 'cm'],
        ]);
        $numeric = $normalizer->normalizeAttribute([
            '__typename' => 'NumericAttribute',
            'code' => 'ratio',
            'unique' => true,
        ]);
        $price = $normalizer->normalizeAttribute([
            '__typename' => 'PriceAttribute',
            'code' => 'rrp',
            'currency' => 'PLN',
        ]);

        self::assertSame('unit', $unit['type']);
        self::assertSame(['unitName' => 'CENTIMETER', 'unitSymbol' => 'cm'], $unit['parameters']);
        self::assertSame('numeric', $numeric['type']);
        self::assertSame(['unique' => true], $numeric['parameters']);
        self::assertSame('price', $price['type']);
        self::assertSame(['currency' => 'PLN'], $price['parameters']);
    }

    public function testRejectsUnknownAttributeRuntimeType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unsupported Ergonode attribute runtime type "BooleanAttribute".');

        $this->normalizer()->normalizeAttribute(['__typename' => 'BooleanAttribute']);
    }

    public function testRejectsMissingAttributeRuntimeType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unsupported Ergonode attribute runtime type "".');

        $this->normalizer()->normalizeAttribute([]);
    }

    public function testAttributeHashIgnoresTranslationAndPayloadFieldOrder(): void
    {
        $normalizer = $this->normalizer();
        $first = $normalizer->normalizeAttribute([
            '__typename' => 'TextAttribute',
            'code' => 'description',
            'scope' => 'global',
            'name' => [
                ['language' => 'pl_PL', 'value' => 'Opis'],
                ['language' => 'en_US', 'value' => 'Description'],
            ],
            'unique' => false,
        ]);
        $second = $normalizer->normalizeAttribute([
            'unique' => false,
            'name' => [
                ['value' => 'Description', 'language' => 'en_US'],
                ['value' => 'Opis', 'language' => 'pl_PL'],
            ],
            'scope' => 'global',
            'code' => 'description',
            '__typename' => 'TextAttribute',
            'metadata' => [['key' => 'ignored', 'value' => 'not persisted']],
        ]);

        self::assertSame($first['hash'], $second['hash']);
    }

    public function testAttributeHashChangesForEveryPersistedStatePart(): void
    {
        $normalizer = $this->normalizer();
        $base = [
            '__typename' => 'TextAttribute',
            'code' => 'description',
            'scope' => 'global',
            'name' => [['language' => 'en_US', 'value' => 'Description']],
            'unique' => false,
        ];
        $baseHash = $normalizer->normalizeAttribute($base)['hash'];

        self::assertNotSame(
            $baseHash,
            $normalizer->normalizeAttribute(array_replace($base, ['code' => 'title']))['hash']
        );
        self::assertNotSame(
            $baseHash,
            $normalizer->normalizeAttribute(array_replace($base, ['scope' => 'local']))['hash']
        );
        self::assertNotSame(
            $baseHash,
            $normalizer->normalizeAttribute(array_replace($base, ['__typename' => 'NumericAttribute']))['hash']
        );
        self::assertNotSame(
            $baseHash,
            $normalizer->normalizeAttribute(array_replace($base, [
                'name' => [['language' => 'en_US', 'value' => 'Changed']],
            ]))['hash']
        );
        self::assertNotSame(
            $baseHash,
            $normalizer->normalizeAttribute(array_replace($base, ['unique' => true]))['hash']
        );
    }

    public function testOptionHashChangesOnlyForPersistedState(): void
    {
        $normalizer = $this->normalizer();
        $node = [
            'code' => 'red',
            'name' => [['language' => 'en_US', 'value' => 'Red']],
        ];
        $baseHash = $normalizer->normalizeOption($node, 1)['hash'];

        self::assertSame(
            $baseHash,
            $normalizer->normalizeOption(array_replace($node, ['ignored' => 'not persisted']), 1)['hash']
        );
        self::assertNotSame(
            $baseHash,
            $normalizer->normalizeOption(array_replace($node, ['code' => 'blue']), 1)['hash']
        );
        self::assertNotSame(
            $baseHash,
            $normalizer->normalizeOption(array_replace($node, [
                'name' => [['language' => 'en_US', 'value' => 'Crimson']],
            ]), 1)['hash']
        );
        self::assertNotSame($baseHash, $normalizer->normalizeOption($node, 2)['hash']);
    }

    private function normalizer(): AttributeNormalizer
    {
        return new AttributeNormalizer(
            new Json(),
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer()
        );
    }
}
