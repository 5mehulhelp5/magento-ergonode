<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\ProductAttributeConsumer\Model\Mapping\BooleanValueNormalizer;
use Ergonode\ProductAttributeConsumer\Model\Mapping\MagentoAttributeTypeRecommender;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MagentoAttributeTypeRecommenderTest extends TestCase
{
    /**
     * @param array<int, array{code: string, labels: array<string, string>}> $options
     */
    #[DataProvider('selectOptionsProvider')]
    public function testRecommendsTypeFromEveryLocalizedOptionLabel(array $options, string $expectedType): void
    {
        $refresher = $this->createMock(AttributeCacheRefresherInterface::class);
        $refresher->expects($this->once())->method('refreshOptions')->with('is_enabled');
        $optionProvider = $this->createStub(ErgonodeOptionProvider::class);
        $optionProvider->method('getOptionDefinitions')->willReturn($options);
        $recommender = new MagentoAttributeTypeRecommender(
            $refresher,
            $optionProvider,
            new BooleanValueNormalizer()
        );

        self::assertSame($expectedType, $recommender->recommend('is_enabled', 'select'));
        self::assertSame($expectedType, $recommender->recommend('is_enabled', 'select'));
    }

    /**
     * @return array<string, array{array<int, array{code: string, labels: array<string, string>}>, string}>
     */
    public static function selectOptionsProvider(): array
    {
        return [
            'single zero' => [[
                ['code' => '0', 'labels' => ['pl_PL' => '0', 'en_GB' => '0']],
            ], 'boolean'],
            'zero and one' => [[
                ['code' => '0', 'labels' => ['pl_PL' => 'Nie', 'de_DE' => 'Nein']],
                ['code' => '1', 'labels' => ['pl_PL' => 'Tak', 'de_DE' => 'Ja']],
            ], 'boolean'],
            'localized arbitrary codes' => [[
                ['code' => 'disabled', 'labels' => ['pl_PL' => 'N', 'en_GB' => 'No']],
                ['code' => 'enabled', 'labels' => ['pl_PL' => 'T', 'en_GB' => 'Yes']],
            ], 'boolean'],
            'ordinary select' => [[
                ['code' => 'red', 'labels' => ['pl_PL' => 'Czerwony']],
                ['code' => 'blue', 'labels' => ['pl_PL' => 'Niebieski']],
            ], 'select'],
            'more than two options' => [[
                ['code' => '0', 'labels' => []],
                ['code' => '1', 'labels' => []],
                ['code' => '2', 'labels' => []],
            ], 'select'],
            'two affirmative synonyms' => [[
                ['code' => 'yes', 'labels' => ['en_GB' => 'Yes']],
                ['code' => 'true', 'labels' => ['en_GB' => 'Y']],
            ], 'select'],
            'conflicting translations' => [[
                ['code' => 'choice', 'labels' => ['en_GB' => 'Yes', 'de_DE' => 'Nein']],
            ], 'select'],
            'unrecognized translation' => [[
                ['code' => '1', 'labels' => ['en_GB' => 'Enabled']],
            ], 'select'],
            'no cached options' => [[], 'select'],
        ];
    }

    public function testDoesNotRefreshOptionsForNonSelectAttribute(): void
    {
        $refresher = $this->createMock(AttributeCacheRefresherInterface::class);
        $refresher->expects($this->never())->method('refreshOptions');
        $optionProvider = $this->createMock(ErgonodeOptionProvider::class);
        $optionProvider->expects($this->never())->method('getOptionDefinitions');

        self::assertSame(
            'text',
            (new MagentoAttributeTypeRecommender(
                $refresher,
                $optionProvider,
                new BooleanValueNormalizer()
            ))->recommend('description', 'text')
        );
    }
}
