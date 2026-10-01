<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\Attribute\Model\AttributeDataNormalizer;
use Ergonode\Attribute\Model\ErgonodeAttributeTypeResolver;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuardFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeStateLoaderTest extends TestCase
{
    public function testReadsUnitIdentityForVerification(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')->willReturnCallback(
            static function (string $document): array {
                self::assertStringContainsString('... on UnitAttribute { unit { name symbol } }', $document);
                return ['attribute_0' => [
                    '__typename' => 'UnitAttribute', 'code' => 'weight', 'scope' => 'LOCAL',
                    'unit' => ['name' => 'KILOGRAM', 'symbol' => 'kg'],
                ]];
            }
        );
        $state = (new AttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer(),
            new CursorPaginationGuardFactory()
        ))->load('weight');
        self::assertNotNull($state);
        self::assertSame(['unitName' => 'KILOGRAM', 'unitSymbol' => 'kg'], $state->getParameters());
    }

    public function testRejectsUnknownAttributeRuntimeType(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn([
            'attribute_0' => ['__typename' => 'BooleanAttribute', 'code' => 'is_enabled'],
        ]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unsupported Ergonode attribute runtime type "BooleanAttribute".');

        (new AttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer(),
            new CursorPaginationGuardFactory()
        ))->load('is_enabled');
    }

    public function testRejectsIncompleteOptionPagination(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturnCallback(static function (string $document): array {
            if (str_contains($document, 'PublisherAttributeOptions')) {
                return ['options_0' => [
                    'edges' => [],
                    'pageInfo' => ['hasNextPage' => true, 'endCursor' => null],
                ]];
            }
            return ['attribute_0' => [
                '__typename' => 'SelectAttribute', 'code' => 'color', 'scope' => 'LOCAL',
                'name' => [], 'metadata' => [],
            ]];
        });
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('invalid pagination');

        (new AttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer(),
            new CursorPaginationGuardFactory()
        ))->load('color');
    }
    public function testRejectsCyclicCursorsBeforeUnboundedRequests(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $pages = 0;
        $client->expects(self::exactly(4))->method('queryWriteScope')->willReturnCallback(
            static function (string $document) use (&$pages): array {
                if (!str_contains($document, 'PublisherAttributeOptions')) {
                    return ['attribute_0' => [
                        '__typename' => 'SelectAttribute', 'code' => 'color', 'scope' => 'LOCAL',
                    ]];
                }
                ++$pages;
                return ['options_0' => [
                    'edges' => [['node' => ['code' => 'option_' . $pages]]],
                    'pageInfo' => ['hasNextPage' => true, 'endCursor' => $pages % 2 === 1 ? 'A' : 'B'],
                ]];
            }
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cyclic cursor');
        (new AttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer(),
            new CursorPaginationGuardFactory()
        ))->load('color');
    }

    public function testMissingOptionPageCannotBeMistakenForAnEmptyList(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturnOnConsecutiveCalls(
            ['attribute_0' => ['__typename' => 'SelectAttribute', 'code' => 'color', 'scope' => 'LOCAL']],
            []
        );
        $this->expectException(LocalizedException::class);
        (new AttributeStateLoader(
            $client,
            new ErgonodeAttributeTypeResolver(),
            new AttributeDataNormalizer(),
            new CursorPaginationGuardFactory()
        ))->load('color');
    }
}
