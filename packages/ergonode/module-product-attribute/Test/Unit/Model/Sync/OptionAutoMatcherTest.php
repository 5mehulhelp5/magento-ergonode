<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Sync;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Sync\OptionAutoMatcher;
use Ergonode\ProductAttribute\Model\Sync\OptionMatchKeyResolver;
use Ergonode\ProductAttribute\Model\Sync\OptionPairPlanner;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class OptionAutoMatcherTest extends TestCase
{
    public function testSuggestsOnlyUnambiguousCodeMatchesAndRejectsTypeMismatches(): void
    {
        $matcher = $this->matcher($this->attributeMapping());

        $result = $matcher->suggest(
            7,
            [
            ['code' => 'bialy', 'label' => 'White', 'type' => 'option'],
            ['code' => 'bialy_copy', 'label' => 'White', 'type' => 'option'],
            ['code' => 'black', 'label' => 'Black', 'type' => 'option'],
            ],
            [
            ['code' => 'option_1', 'label' => ' BIALY ', 'type' => 'option'],
            ['code' => 'option_2', 'label' => 'Black', 'type' => 'option'],
            ]
        );

        self::assertSame(
            [
            ['bialy', 'option_1'],
            ['black', 'option_2'],
            ],
            array_map(
                static fn (array $match): array => [$match['left']['code'], $match['right']['code']],
                $result['matches']
            )
        );
    }

    public function testUsesCanonicalBooleanAndVisibilityRules(): void
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
        $visibility = $this->matcher(
            $this->attributeMapping(
                [
                'ergonode_attribute_code' => 'visibility',
                'magento_attribute_code' => 'visibility',
                ]
            )
        );
        $visibilityResult = $visibility->suggest(
            7,
            [
            ['code' => 'option_4', 'label' => 'Catalog, Search', 'type' => 'option'],
            ],
            [
            ['code' => '4', 'label' => 'Catalog, Search', 'type' => 'option'],
            ]
        );

        self::assertCount(2, $booleanResult['matches']);
        self::assertSame('1', $booleanResult['matches'][0]['right']['code']);
        self::assertCount(1, $visibilityResult['matches']);
        self::assertSame('4', $visibilityResult['matches'][0]['right']['code']);
    }

    public function testMatchesCodeThenExplicitStoreLabelInSameLanguage(): void
    {
        $result = $this->matcher($this->attributeMapping())->suggest(
            7,
            [
            ['code' => 'blue', 'names' => ['pl_PL' => 'Niebieski', 'en_US' => 'Blue'], 'type' => 'option'],
            ['code' => 'legacy_59', 'names' => ['pl_PL' => 'To & owo'], 'type' => 'option'],
            ['code' => 'without_translation', 'names' => ['en_US' => 'Orphan'], 'type' => 'option'],
            ],
            [
            ['code' => 'option_10', 'label' => 'Blue', 'type' => 'option'],
            ['code' => 'option_11', 'label' => 'Unknown', 'store_labels' => [2 => ' To  & owo '], 'type' => 'option'],
            ['code' => 'option_12', 'label' => 'Orphan', 'type' => 'option'],
            ],
            [1 => 'en_US', 2 => 'pl_PL', 3 => 'pl_PL']
        );

        self::assertSame(
            [
            ['blue', 'option_10'],
            ['legacy_59', 'option_11'],
            ],
            array_map(
                static fn (array $match): array => [$match['left']['code'], $match['right']['code']],
                $result['matches']
            )
        );
        self::assertContains('without_translation', $result['unmatched']);
    }

    public function testKeepsValidPairsWhenAnotherKeyIsAmbiguous(): void
    {
        $result = $this->matcher($this->attributeMapping())->suggest(
            7,
            [
                ['code' => 'red', 'type' => 'option'],
                ['code' => 'green', 'type' => 'option'],
                ['code' => 'blue', 'type' => 'option'],
            ],
            [
                ['code' => 'option_1', 'label' => 'Red', 'type' => 'option'],
                ['code' => 'option_2', 'label' => 'RED', 'type' => 'option'],
                ['code' => 'option_3', 'label' => 'Green', 'type' => 'option'],
            ]
        );

        self::assertSame('red', array_key_first($result['conflicts']));
        self::assertSame('blue', $result['unmatched'][0]);
        self::assertSame('green', $result['matches'][0]['left']['code']);
    }

    public function testLaterLanguageCannotReuseAnOptionAlreadyPairedAtStoreZero(): void
    {
        $result = $this->matcher($this->attributeMapping())->suggest(
            7,
            [
                ['code' => 'blue', 'names' => ['pl_PL' => 'Niebieski'], 'type' => 'option'],
                ['code' => 'legacy', 'names' => ['pl_PL' => 'Niebieski'], 'type' => 'option'],
                ['code' => 'green', 'type' => 'option'],
            ],
            [
                ['code' => 'option_1', 'label' => 'Blue', 'store_labels' => [2 => 'Niebieski'], 'type' => 'option'],
                ['code' => 'option_2', 'label' => 'Green', 'type' => 'option'],
            ],
            [2 => 'pl_PL']
        );

        self::assertSame(['blue', 'green'], array_column(array_column($result['matches'], 'left'), 'code'));
        self::assertArrayHasKey('legacy', $result['conflicts']);
    }

    public function testSavedPairReservesItsMagentoOption(): void
    {
        $result = $this->matcher($this->attributeMapping(), [[
            'ergonode_option_code' => 'blue', 'magento_option_id' => 10, 'status' => 'complete',
        ]])->suggest(
            7,
            [
                ['code' => 'blue', 'type' => 'option'],
                ['code' => 'legacy', 'names' => ['pl_PL' => 'Niebieski'], 'type' => 'option'],
            ],
            [['code' => 'option_10', 'label' => 'Blue', 'store_labels' => [2 => 'Niebieski'], 'type' => 'option']],
            [2 => 'pl_PL']
        );

        self::assertSame([], $result['matches']);
        self::assertArrayHasKey('legacy', $result['conflicts']);
    }

    public function testSavedBooleanZeroIsNotSuggestedAgain(): void
    {
        $result = $this->matcher(
            $this->attributeMapping(['magento_type' => 'boolean']),
            [['ergonode_option_code' => 'no', 'magento_option_id' => 0, 'status' => 'complete']]
        )->suggest(
            7,
            [['code' => 'no', 'label' => 'Nie', 'type' => 'option']],
            [['code' => 'option_0', 'label' => 'No', 'type' => 'option']]
        );

        self::assertSame([], $result['matches']);
    }

    public function testMissingMappingIsRejected(): void
    {
        $provider = $this->createStub(MappingReaderInterface::class);
        $provider->method('getAttributeRow')->willReturn(null);
        $compatibility = $this->createMock(AttributeTypeCompatibilityInterface::class);
        $compatibility->expects(self::never())->method('canMapOptions');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('does not exist');

        (new OptionAutoMatcher($provider, $compatibility, new OptionPairPlanner(new OptionMatchKeyResolver())))
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

    /**
     * @param array<string, mixed> $mapping
     * @param array<int, array<string, mixed>> $savedRows
     */
    private function matcher(array $mapping, array $savedRows = []): OptionAutoMatcher
    {
        $provider = $this->createStub(MappingReaderInterface::class);
        $provider->method('getAttributeRow')->willReturn($mapping);
        $provider->method('getOptionRows')->willReturn($savedRows);
        $compatibility = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $compatibility->method('canMapOptions')->willReturn(true);

        return new OptionAutoMatcher($provider, $compatibility, new OptionPairPlanner(new OptionMatchKeyResolver()));
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
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            ],
            $overrides
        );
    }
}
