<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Contract;

use Ergonode\ProductConsumer\Model\GraphQl\ProductQueries;
use PHPUnit\Framework\TestCase;

class ProductSchemaContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $schema;

    protected function setUp(): void
    {
        $this->schema = json_decode((string)file_get_contents(
            dirname(__DIR__, 6) . '/vendor/ergonode/module-publisher/Test/Contract/Fixture/ergonode-schema.json'
        ), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testPinnedSchemaSupportsConsumerStreamsAndProductTypes(): void
    {
        $queries = $this->schema['domains']['products']['queries'];
        self::assertSame('ProductConnection', $queries['productStream']['returns']);
        self::assertSame('ProductDeletedConnection', $queries['productDeletedStream']['returns']);
        self::assertSame('Product', $queries['product']['returns']);
        self::assertSame(
            ['GroupingProduct', 'SimpleProduct', 'VariableProduct'],
            $this->schema['types']['Product']['possibleTypes']
        );
        self::assertArrayHasKey('variantList', $this->schema['types']['VariableProduct']['fields']);
        self::assertArrayHasKey('childrenList', $this->schema['types']['GroupingProduct']['fields']);
        self::assertArrayHasKey('isVariant', $this->schema['types']['SimpleProduct']['fields']);
        self::assertArrayHasKey('createdAt', $this->schema['types']['Product']['fields']);
        self::assertArrayHasKey('editedAt', $this->schema['types']['Product']['fields']);
    }

    public function testQueriesKeepBoundedNestedConnectionsAndCursors(): void
    {
        self::assertStringContainsString('productStream(first: $first, after: $after)', ProductQueries::PRODUCT_STREAM);
        self::assertStringContainsString(
            'productDeletedStream(first: $first, after: $after)',
            ProductQueries::PRODUCT_DELETED_STREAM
        );
        self::assertStringContainsString('attributeList(first: 100', ProductQueries::PRODUCT_STREAM);
        self::assertStringNotContainsString('categoryList', ProductQueries::PRODUCT_STREAM);
        self::assertStringContainsString('pageInfo { hasNextPage endCursor }', ProductQueries::PRODUCT_STREAM);
    }
}
