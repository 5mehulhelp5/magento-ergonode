<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;

use Ergonode\CategoryAttributeConsumer\Model\Import\CategoryEntityNormalizer;

use Ergonode\Attribute\Model\AttributeValueNormalizer;

use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\Category\Model\Import\PaginationStateResolver;
use Ergonode\CategoryAttributeConsumer\Model\Import\CategoryEntityLoader;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryEntityLoaderTest extends TestCase
{
    public function testLoadsValuesFromTypeSpecificTranslationAlias(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('query')
            ->with(self::stringContains('category_0: category'), [
                'languages' => ['pl_PL'],
                'first' => 100,
                'code_0' => 'chairs',
                'after_0' => null,
            ])
            ->willReturn(['category_0' => [
                'code' => 'chairs',
                'name' => [['language' => 'pl_PL', 'value' => 'Krzesła']],
                'attributeList' => [
                    'pageInfo' => ['hasNextPage' => false],
                    'edges' => [[
                        'node' => [
                            '__typename' => 'MultiSelectAttributeValue',
                            'attribute' => ['code' => 'colors'],
                            'multiSelectAttributeValueTranslations' => [[
                                'language' => 'pl_PL',
                                'value' => [['code' => 'red'], ['code' => 'blue']],
                            ]],
                        ],
                    ]],
                ],
            ]]);
        $languageMapping = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMapping->method('getLanguageCodes')->willReturn(['pl_PL']);
        $normalizer = new AttributeValueNormalizer();

        $entity = (new CategoryEntityLoader(
            $this->createMock(CategoryAttributeSourcePreparation::class),
            $client,
            $languageMapping,
            new CategoryEntityNormalizer(new ErgonodeAttributeTypeResolver(), $normalizer, $normalizer, new Json()),
            new PaginationStateResolver(),
            new CategoryQueries()
        ))->load('chairs');

        self::assertNotNull($entity);
        self::assertSame([
            'code' => 'colors',
            'type' => 'multi_select',
            'values' => ['pl_PL' => ['red', 'blue']],
        ], $entity['attributes'][0]);
    }

    public function testBatchContinuesOnlyUnfinishedCategoriesWithTheirOwnCursors(): void
    {
        $client = $this->createMock(Client::class);
        $request = 0;
        $client->expects(self::never())->method('queryWriteScope');
        $client->expects(self::exactly(3))->method('query')->willReturnCallback(
            static function (string $query, array $variables) use (&$request): array {
                $request++;
                if ($request === 1) {
                    self::assertSame('a', $variables['code_0']);
                    self::assertSame('b', $variables['code_1']);
                    self::assertNull($variables['after_0']);
                    self::assertNull($variables['after_1']);
                    return [
                        'category_0' => self::page('a', true, 'a-1'),
                        'category_1' => self::page('b', true, 'b-1'),
                    ];
                }
                if ($request === 2) {
                    self::assertSame('a-1', $variables['after_0']);
                    self::assertSame('b-1', $variables['after_1']);
                    return ['category_0' => self::page('a', false, 'a-2'),
                        'category_1' => self::page('b', true, 'b-2')];
                }
                self::assertSame('b', $variables['code_0']);
                self::assertSame('b-2', $variables['after_0']);
                self::assertArrayNotHasKey('code_1', $variables);
                return ['category_0' => self::page('b', false, 'b-3')];
            }
        );
        $preparation = $this->createMock(CategoryAttributeSourcePreparation::class);
        $preparation->expects(self::once())->method('ensurePrepared');
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL']);
        $normalizer = new AttributeValueNormalizer();
        $loader = new CategoryEntityLoader(
            $preparation,
            $client,
            $languages,
            new CategoryEntityNormalizer(new ErgonodeAttributeTypeResolver(), $normalizer, $normalizer, new Json()),
            new PaginationStateResolver(),
            new CategoryQueries()
        );
        $entities = $loader->loadMany(['a', 'b', 'a']);
        self::assertCount(2, $entities);
        self::assertCount(2, $entities['a']['attributes']);
        self::assertCount(3, $entities['b']['attributes']);
    }

    private static function page(string $code, bool $more, string $cursor): array
    {
        return ['code' => $code, 'name' => [], 'attributeList' => [
            'pageInfo' => ['hasNextPage' => $more, 'endCursor' => $cursor],
            'edges' => [['node' => ['__typename' => 'TextAttributeValue', 'attribute' => ['code' => $cursor],
                'textAttributeValueTranslations' => [['language' => 'pl_PL', 'value' => $cursor]]]]],
        ]];
    }
}
