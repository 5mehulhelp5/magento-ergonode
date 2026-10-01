<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Model\Source;

use Ergonode\ProductCategoryPublisher\Api\Data\ProductCategoryStateInterface;
use Ergonode\ProductCategoryPublisher\Model\Data\ProductCategoryState;
use Ergonode\ProductCategoryPublisher\Model\Source\ProductCategoryPreparedStateDecorator;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use PHPUnit\Framework\TestCase;

class ProductCategoryPreparedStateDecoratorTest extends TestCase
{
    public function testRestoresCategoryStateAfterRemoteIdentityDecoration(): void
    {
        $input = new ProductCategoryState(new ProductState('MAGENTO-1', 'simple', 'default'), ['chairs'], true);
        $prepared = new ProductState('ERG-1', 'simple', 'default');

        $result = (new ProductCategoryPreparedStateDecorator())->decorate($input, $prepared);

        self::assertInstanceOf(ProductCategoryStateInterface::class, $result);
        self::assertSame('ERG-1', $result->getSku());
        self::assertSame(['chairs'], $result->getCategoryCodes());
    }
}
