<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Gallery;

use Ergonode\ProductMedia\Model\ResourceModel\RoleWriter;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use PHPUnit\Framework\TestCase;

class RoleWriterTest extends TestCase
{
    public function testUnchangedRoleDoesNotWriteAndManualOverrideIsReplaced(): void
    {
        $products = $this->createMock(Product::class);
        $products->expects(self::exactly(2))->method('getAttributeRawValue')
            ->willReturnOnConsecutiveCalls('/a.jpg', '/manual.jpg');
        $action = $this->createMock(Action::class);
        $action->expects(self::once())->method('updateAttributes')->with([9], ['image' => '/a.jpg'], 0);
        $writer = new RoleWriter($products, $action);
        $writer->write(9, 0, ['image' => 'catalog/product/a.jpg']);
        $writer->write(9, 0, ['image' => 'catalog/product/a.jpg']);
    }
}
