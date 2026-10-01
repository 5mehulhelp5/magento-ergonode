<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Contract;

use Ergonode\ProductCategoryPublisher\Model\GraphQl\ProductCategoryMutationBuilder;
use PHPUnit\Framework\TestCase;

class SchemaContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $schema;

    protected function setUp(): void
    {
        $this->schema = json_decode((string)file_get_contents(
            dirname(__DIR__, 6) . '/vendor/ergonode/module-publisher/Test/Contract/Fixture/ergonode-schema.json'
        ), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCategoryRelationQueriesAndMutationsMatchPinnedSchema(): void
    {
        self::assertArrayHasKey('categoryList', $this->schema['types']['Product']['fields']);
        $factory = new ProductCategoryMutationBuilder();

        foreach ([$factory->add('SKU-1', ['chairs']), $factory->remove('SKU-1', ['legacy'])] as $operation) {
            $field = $operation->getField();
            $signature = $this->schema['domains']['products']['mutations'][$field];
            $variable = $operation->getVariables()['input'];
            self::assertSame($signature['arguments']['input']['type'], $variable->getType());
            self::assertSame(
                ['sku' => 'SKU-1', 'categoryCodes' => $field === 'productAddCategories' ? ['chairs'] : ['legacy']],
                $variable->getValue()
            );
        }
    }
}
