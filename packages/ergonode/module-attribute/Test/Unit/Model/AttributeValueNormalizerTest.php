<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Test\Unit\Model;

use Ergonode\Attribute\Model\AttributeValueNormalizer;
use PHPUnit\Framework\TestCase;

class AttributeValueNormalizerTest extends TestCase
{
    public function testFileListsPreserveAllPathsWhileImagesRemainScalar(): void
    {
        $normalizer = new AttributeValueNormalizer();
        foreach ([[], [['path' => '/one.pdf']], [
            ['path' => ' /one.pdf '], ['path' => '/two.pdf'], ['path' => '/one.pdf'], ['path' => ''],
        ]] as $index => $files) {
            $expected = [[], ['/one.pdf'], ['/one.pdf', '/two.pdf']][$index];
            self::assertSame(['pl_PL' => $expected], $normalizer->valueTranslations('file', [
                ['language' => 'pl_PL', 'value' => $files],
            ]));
        }
        self::assertSame(['pl_PL' => '/image.jpg'], $normalizer->valueTranslations('image', [
            ['language' => 'pl_PL', 'value' => ['path' => ' /image.jpg ']],
        ]));
    }

    public function testNormalizesReferenceValuesAndRejectsIncompleteTranslations(): void
    {
        $normalizer = new AttributeValueNormalizer();

        self::assertSame(
            ['pl_PL' => ['red', 'blue']],
            $normalizer->valueTranslations('multi_select', [
                ['language' => 'pl_PL', 'value' => [
                    ['code' => ' red '],
                    ['code' => ''],
                    ['code' => 'blue'],
                    ['code' => 'red'],
                ]],
                ['language' => '', 'value' => [['code' => 'ignored']]],
                ['language' => 'en_GB', 'value' => null],
            ])
        );
    }

    public function testNormalizesTextTranslations(): void
    {
        self::assertSame(
            ['pl_PL' => 'Krzesła'],
            (new AttributeValueNormalizer())->translations([
                ['language' => ' pl_PL ', 'value' => 'Krzesła'],
                ['language' => '', 'value' => 'Ignored'],
            ])
        );
    }
}
