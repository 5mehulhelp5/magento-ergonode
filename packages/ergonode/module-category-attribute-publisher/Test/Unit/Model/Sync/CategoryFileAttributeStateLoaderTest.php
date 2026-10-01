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

class CategoryFileAttributeStateLoaderTest extends TestCase
{
    public function testFileListCanBeLoadedIntoPublicationState(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $category = [
            'code' => 'chairs',
            'attributeList' => [
                'edges' => [['node' => [
                    '__typename' => 'FileAttributeValue',
                    'attribute' => ['code' => 'manual'],
                    'fileAttributeValueTranslations' => [[
                        'language' => 'pl_PL',
                        'value' => [['path' => ' /one.pdf '], ['path' => '/two.pdf']],
                    ]],
                ]]],
                'pageInfo' => ['hasNextPage' => false],
            ],
        ];
        $client->expects(self::once())->method('queryWriteScope')->willReturnCallback(
            static function (string $query) use ($category): array {
                // Keep the value regression independent of optional GraphQL read batching.
                preg_match('/(?:(\w+):\s*)?category\(code:/', $query, $match);
                return [($match[1] ?? '') ?: 'category' => $category];
            }
        );
        $registryLoader = $this->createMock(WriteScopeCategoryAttributeCodeLoaderInterface::class);
        $registryLoader->expects(self::once())->method('loadWriteScope')->willReturn(['manual']);

        $state = (new CategoryAttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeValueNormalizer(),
            $registryLoader,
            new CursorPaginationGuardFactory()
        ))->load('chairs', ['pl_PL']);

        self::assertSame(['manual'], $state->getAllowedAttributeCodes());
        self::assertSame('file', $state->getValues()[0]->getType());
        self::assertSame(['pl_PL' => ['/one.pdf', '/two.pdf']], $state->getValues()[0]->getTranslations());
    }
}
