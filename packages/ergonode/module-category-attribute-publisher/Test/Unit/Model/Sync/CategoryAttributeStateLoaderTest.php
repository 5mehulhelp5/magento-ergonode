<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Model\Sync;

use Ergonode\Attribute\Model\AttributeValueNormalizer;
use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributePublisher\Model\Sync\CategoryAttributeStateLoader;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuardFactory;
use PHPUnit\Framework\TestCase;

class CategoryAttributeStateLoaderTest extends TestCase
{
    public function testUsesSharedTypeAndValueNormalization(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')->willReturn(['category_0' => [
            'code' => 'chairs',
            'attributeList' => [
                'edges' => [['node' => [
                    '__typename' => 'MultiSelectAttributeValue',
                    'attribute' => ['code' => 'colors'],
                    'multiSelectAttributeValueTranslations' => [[
                        'language' => 'pl_PL',
                        'value' => [['code' => ' red '], ['code' => 'blue'], ['code' => 'red']],
                    ]],
                ]]],
                'pageInfo' => ['hasNextPage' => false],
            ],
        ]]);
        $registryLoader = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registryLoader->expects(self::once())->method('loadWriteScope')->willReturn(['colors']);

        $state = (new CategoryAttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeValueNormalizer(),
            $registryLoader,
            new CursorPaginationGuardFactory()
        ))->load('chairs', ['pl_PL']);

        self::assertSame(['colors'], $state->getAllowedAttributeCodes());
        self::assertSame('multi_select', $state->getValues()[0]->getType());
        self::assertSame(['pl_PL' => ['red', 'blue']], $state->getValues()[0]->getTranslations());
    }

    public function testPaginationOnlyContinuesUnfinishedCategoriesAndSplitsAtFifty(): void
    {
        $requests = [];
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(3))->method('queryWriteScope')->willReturnCallback(
            static function (string $query, array $variables) use (&$requests): array {
                $data = [];
                for ($i = 0; isset($variables['code_' . $i]); $i++) {
                    $code = $variables['code_' . $i];
                    self::assertSame($code === 'c_0' ? null : ['pl_PL'], $variables['languages_' . $i]);
                    $more = $code === 'c_0' && $variables['after_' . $i] === null;
                    $data['category_' . $i] = [
                        'code' => $code,
                        'attributeList' => ['edges' => [], 'pageInfo' => [
                            'hasNextPage' => $more, 'endCursor' => $more ? 'next' : null,
                        ]],
                    ];
                }
                $requests[] = $variables;
                return $data;
            }
        );
        $registry = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registry->expects(self::once())->method('loadWriteScope')->willReturn([]);
        $loader = new CategoryAttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeValueNormalizer(),
            $registry,
            new CursorPaginationGuardFactory()
        );
        $scopes = array_fill_keys(array_map(static fn (int $i): string => 'c_' . $i, range(0, 50)), ['pl_PL']);
        $scopes['c_0'] = [];
        self::assertCount(51, $loader->loadBatch($scopes));
        self::assertCount(151, $requests[0]);
        self::assertSame(['first' => 100, 'code_0' => 'c_0', 'languages_0' => null, 'after_0' => 'next'], $requests[1]);
        self::assertSame('c_50', $requests[2]['code_0']);
    }

    public function testFiftyCategoriesShareValueAndRegistryReadsButRefreshOnNextCall(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            static function (string $query, array $variables): array {
                self::assertSame(50, substr_count($query, ': category(code:'));
                $data = [];
                for ($i = 0; $i < 50; $i++) {
                    self::assertSame(['pl_PL'], $variables['languages_' . $i]);
                    $data['category_' . $i] = [
                        'code' => $variables['code_' . $i],
                        'attributeList' => ['edges' => [], 'pageInfo' => ['hasNextPage' => false]],
                    ];
                }
                return $data;
            }
        );
        $registry = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registry->expects(self::exactly(2))->method('loadWriteScope')->willReturnOnConsecutiveCalls([], ['colors']);
        $loader = new CategoryAttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeValueNormalizer(),
            $registry,
            new CursorPaginationGuardFactory()
        );
        $scopes = array_fill_keys(array_map(static fn (int $i): string => 'c_' . $i, range(1, 50)), ['pl_PL']);
        foreach ($loader->loadBatch($scopes) as $state) {
            self::assertSame([], $state->getAllowedAttributeCodes());
        }
        foreach ($loader->loadBatch($scopes) as $state) {
            self::assertSame(['colors'], $state->getAllowedAttributeCodes());
        }
    }
}
