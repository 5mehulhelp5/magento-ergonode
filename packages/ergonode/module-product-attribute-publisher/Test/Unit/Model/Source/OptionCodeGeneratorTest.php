<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Unit\Model\Source;

use Ergonode\ProductAttributePublisher\Model\Source\OptionCodeGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\TranslitUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OptionCodeGeneratorTest extends TestCase
{
    #[DataProvider('labels')]
    public function testGeneratesHumanReadableCodes(string $label, string $expected): void
    {
        self::assertSame($expected, $this->generator()->generate($label));
    }

    /** @return array<string, array{string, string}> */
    public static function labels(): array
    {
        return [
            'english' => ['White', 'white'],
            'polish' => ['Czerwony', 'czerwony'],
            'separators and diacritics' => ['Wysyłka InPost', 'wysylka_inpost'],
            'collapsed separators' => ['  Blue / Navy -- XL  ', 'blue_navy_xl'],
            'unsupported punctuation' => ['ala ma kota ***', 'ala_ma_kota'],
        ];
    }

    public function testRejectsLabelWithoutCodeCharacters(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cannot be converted');

        $this->generator()->generate('---');
    }

    private function generator(): OptionCodeGenerator
    {
        return new OptionCodeGenerator(new TranslitUrl($this->createStub(ScopeConfigInterface::class)));
    }
}
