<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Test\Unit\Model\Sync;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Model\Sync\OptionAutoMatcher;
use Ergonode\CategoryAttribute\Model\Sync\OptionMatchKeyResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class OptionAutoMatcherTest extends TestCase
{
    public function testSuggestsBackendMatchesInSourceOrderAndRejectsTypeMismatches(): void
    {
        $matcher = $this->matcher($this->attributeMapping());

        $result = $matcher->suggest(
            7,
            [
            ['code' => 'left_1', 'label' => 'Biały', 'type' => 'option', 'source' => 'ergo'],
            ['code' => 'left_2', 'label' => 'BIAŁY', 'type' => 'option', 'source' => 'ergo'],
            ['code' => 'left_3', 'label' => 'Biały', 'type' => 'other', 'source' => 'ergo'],
            ],
            [
            ['code' => 'right_1', 'label' => 'bialy', 'type' => 'option', 'source' => 'magento'],
            ['code' => 'right_2', 'label' => 'Biały', 'type' => 'option', 'source' => 'magento'],
            ]
        );

        self::assertSame(
            [
            ['left_1', 'right_1'],
            ['left_2', 'right_2'],
            ],
            array_map(
                static fn (array $match): array => [$match['left']['code'], $match['right']['code']],
                $result['matches']
            )
        );
    }

    public function testUsesCanonicalBooleanRules(): void
    {
        $boolean = $this->matcher($this->attributeMapping(['magento_type' => 'boolean']));
        $booleanResult = $boolean->suggest(
            7,
            [
            ['code' => 'option_yes', 'label' => 'Tak', 'type' => 'option'],
            ['code' => 'option_no', 'label' => 'Nie', 'type' => 'option'],
            ],
            [
            ['code' => '1', 'label' => 'Yes', 'type' => 'option'],
            ['code' => '0', 'label' => 'No', 'type' => 'option'],
            ]
        );
        self::assertCount(2, $booleanResult['matches']);
        self::assertSame('1', $booleanResult['matches'][0]['right']['code']);
        self::assertSame('0', $booleanResult['matches'][1]['right']['code']);
    }

    public function testMatchesErgonodeCodeBeforeFallingBackToErgonodeLabel(): void
    {
        $result = $this->matcher($this->attributeMapping())->suggest(
            7,
            [
            ['code' => 'blue', 'label' => 'Navy', 'type' => 'option'],
            ['code' => 'navy', 'label' => 'Blue', 'type' => 'option'],
            ['code' => 'wysylka_inpost', 'label' => 'Kurier', 'type' => 'option'],
            ['code' => 'legacy_59', 'label' => 'White', 'type' => 'option'],
            ],
            [
            ['code' => 'option_10', 'label' => 'Blue', 'type' => 'option'],
            ['code' => 'option_11', 'label' => 'Navy', 'type' => 'option'],
            ['code' => 'option_12', 'label' => 'Wysyłka InPost', 'type' => 'option'],
            ['code' => 'option_13', 'label' => 'White', 'type' => 'option'],
            ]
        );

        self::assertSame(
            [
            ['blue', 'option_10'],
            ['navy', 'option_11'],
            ['wysylka_inpost', 'option_12'],
            ['legacy_59', 'option_13'],
            ],
            array_map(
                static fn (array $match): array => [$match['left']['code'], $match['right']['code']],
                $result['matches']
            )
        );
    }

    public function testMissingMappingIsRejected(): void
    {
        $provider = $this->createStub(MappingReaderInterface::class);
        $provider->method('getAttributeRow')->willReturn(null);
        $compatibility = $this->createMock(AttributeTypeCompatibilityInterface::class);
        $compatibility->expects(self::never())->method('canMapOptions');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('does not exist');

        (new OptionAutoMatcher($provider, $compatibility, new OptionMatchKeyResolver()))
            ->suggest(999, [], []);
    }

    public function testMatchesLargeUniqueOptionSetWithoutNestedCandidateScan(): void
    {
        $options = array_map(
            static fn (int $index): array => [
            'code' => 'option_' . $index,
            'label' => 'Option ' . $index,
            'type' => 'option',
            ],
            range(1, 5000)
        );

        self::assertCount(5000, $this->matcher($this->attributeMapping())->suggest(7, $options, $options)['matches']);
    }

    public function testDraftAttributeMappingIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('saved category attribute mapping');
        $this->matcher($this->attributeMapping(['status' => 'draft']))->suggest(7, [], []);
    }

    public function testIncompatibleAttributeTypesAreRejected(): void
    {
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRow')->willReturn($this->attributeMapping());
        $compatibility = $this->createMock(AttributeTypeCompatibilityInterface::class);
        $compatibility->expects(self::once())->method('canMapOptions')->with('select', 'select')->willReturn(false);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('option-mappable');
        (new OptionAutoMatcher($reader, $compatibility, new OptionMatchKeyResolver()))->suggest(7, [], []);
    }

    public function testCodeMatchesReserveTargetsBeforeEarlierLabelFallbacks(): void
    {
        $result = $this->matcher($this->attributeMapping())->suggest(7, [
            ['code' => 'legacy', 'label' => 'Blue'],
            ['code' => 'blue', 'label' => 'Different label'],
            ['code' => '', 'label' => 'Red'],
        ], [
            ['code' => 'option_1', 'label' => 'Blue'],
            ['code' => 'option_2', 'label' => 'Red'],
        ]);
        self::assertCount(1, $result['matches']);
        self::assertSame('blue', $result['matches'][0]['left']['code']);
        self::assertSame('option_1', $result['matches'][0]['right']['code']);
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function matcher(array $mapping): OptionAutoMatcher
    {
        $provider = $this->createStub(MappingReaderInterface::class);
        $provider->method('getAttributeRow')->willReturn($mapping);
        $compatibility = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $compatibility->method('canMapOptions')->willReturn(true);

        return new OptionAutoMatcher($provider, $compatibility, new OptionMatchKeyResolver());
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function attributeMapping(array $overrides = []): array
    {
        return array_replace(
            [
            'mapping_id' => 7,
            'status' => 'complete',
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            ],
            $overrides
        );
    }
}
