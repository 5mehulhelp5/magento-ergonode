<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Attribute\Model\AttributeValueNormalizer;
use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\CategoryAttributeConsumer\Model\Import\CategoryEntityNormalizer;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryFileValueNormalizerTest extends TestCase
{
    public function testImportPreservesFilePathsForTheCategoryMapper(): void
    {
        $values = new AttributeValueNormalizer();
        $normalizer = new CategoryEntityNormalizer(new ErgonodeAttributeTypeResolver(), $values, $values, new Json());
        $category = $normalizer->normalize([
            'code' => 'chairs',
            'attributeList' => ['edges' => [['node' => [
                '__typename' => 'FileAttributeValue',
                'attribute' => ['code' => 'manual'],
                'fileAttributeValueTranslations' => [[
                    'language' => 'pl_PL',
                    'value' => [['path' => '/one.pdf'], ['path' => '/two.pdf']],
                ]],
            ]]]],
        ]);

        self::assertSame([[
            'code' => 'manual', 'type' => 'file', 'values' => ['pl_PL' => ['/one.pdf', '/two.pdf']],
        ]], $category['attributes']);
    }
}
