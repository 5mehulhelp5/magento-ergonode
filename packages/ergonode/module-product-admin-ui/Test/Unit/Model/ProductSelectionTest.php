<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Model;

use Ergonode\Product\Api\ProductCatalogInterface;
use Ergonode\ProductAdminUi\Model\ProductSelection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ProductSelectionTest extends TestCase
{
    public function testAllSelectionPreservesFiltersAndExclusions(): void
    {
        $catalog = $this->createMock(ProductCatalogInterface::class);
        $catalog->expects(self::once())->method('getIds')
            ->with('shirt', [], [7], true, ['type_id' => 'simple'])->willReturn([3, 8]);
        $selection = new ProductSelection($catalog, new Json());
        self::assertSame([3, 8], $selection->snapshot(
            '{"all":true,"search":"shirt","selected":[],"excluded":[7],"criteria":{"type_id":"simple"}}'
        ));
    }

    public function testMalformedIdIsNotSilentlyCoercedToARealProduct(): void
    {
        $selection = new ProductSelection($this->createStub(ProductCatalogInterface::class), new Json());
        $this->expectException(LocalizedException::class);
        $selection->decodeIds('["1x"]');
    }
}
