<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class MagentoSkuSynchronizerTest extends TestCase
{
    public function testMappedIdentityAttributeCannotAlsoBeAnOrdinaryValueMapping(): void
    {
        $attribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $attribute->method('getCode')->willReturn('navireo_id');
        $synchronizer = new MagentoSkuSynchronizer(
            $this->createStub(ResourceConnection::class),
            $this->createStub(ProductRepositoryInterface::class),
            $this->createStub(ProductIdentityServiceInterface::class),
            $attribute,
            $this->createStub(ProductIdentityModeProviderInterface::class)
        );

        $synchronizer->assertIdentityAttributeNotMapped(
            ['values' => ['navireo_id' => [0 => 'wrong']], 'clear' => []],
            ProductIdentityInterface::MODE_SHARED
        );
        $this->expectException(LocalizedException::class);
        $synchronizer->assertIdentityAttributeNotMapped(
            ['values' => ['navireo_id' => [0 => 'wrong']], 'clear' => []],
            ProductIdentityInterface::MODE_MAPPED
        );
    }

    public function testOnlyMappedModeWritesNativeSkuToMagentoIdentityAttribute(): void
    {
        $attribute = $this->createMock(MagentoIdentityAttributeInterface::class);
        $attribute->expects(self::once())->method('write')->with(23, 'NAV-1');
        $synchronizer = new MagentoSkuSynchronizer(
            $this->createStub(ResourceConnection::class),
            $this->createStub(ProductRepositoryInterface::class),
            $this->createStub(ProductIdentityServiceInterface::class),
            $attribute,
            $this->createStub(ProductIdentityModeProviderInterface::class)
        );

        $synchronizer->synchronizeIdentityAttribute(23, 'NAV-1', ProductIdentityInterface::MODE_SHARED);
        $synchronizer->synchronizeIdentityAttribute(23, 'NAV-1', ProductIdentityInterface::MODE_MAPPED);
    }
}
