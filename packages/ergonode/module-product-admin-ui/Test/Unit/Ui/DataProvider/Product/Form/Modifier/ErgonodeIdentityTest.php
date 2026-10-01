<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductAdminUi\Ui\DataProvider\Product\Form\Modifier\ErgonodeIdentity;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class ErgonodeIdentityTest extends TestCase
{
    public function testUnboundMappedProductPreviewsAttributeWithoutClaimingItIsPublished(): void
    {
        $modifier = $this->modifier('mapped', [], '385');
        $meta = $modifier->modifyMeta([]);
        $data = $modifier->modifyData([1 => ['product' => []]]);

        self::assertArrayHasKey('planned_ergonode_sku', $meta['ergonode_identity']['children']);
        self::assertSame('', $data[1]['product']['ergonode_sku'] ?? '');
        self::assertSame('385', $data[1]['product']['planned_ergonode_sku']);
    }

    public function testExistingBindingShowsOnlySavedNativeSku(): void
    {
        $identity = $this->createStub(ProductIdentityInterface::class);
        $identity->method('getErgonodeSku')->willReturn('1000000160');
        $modifier = $this->modifier('mapped', [1 => $identity], '385');
        $meta = $modifier->modifyMeta([]);
        $data = $modifier->modifyData([1 => ['product' => []]]);

        self::assertArrayNotHasKey('planned_ergonode_sku', $meta['ergonode_identity']['children']);
        self::assertSame('1000000160', $data[1]['product']['ergonode_sku']);
        self::assertArrayNotHasKey('planned_ergonode_sku', $data[1]['product']);
    }

    public function testAssignedModeDoesNotPresentMagentoAttributeAsNativeSku(): void
    {
        $modifier = $this->modifier('assigned', [], '385');
        $meta = $modifier->modifyMeta([]);
        $data = $modifier->modifyData([1 => ['product' => []]]);

        self::assertArrayNotHasKey('planned_ergonode_sku', $meta['ergonode_identity']['children']);
        self::assertArrayNotHasKey('planned_ergonode_sku', $data[1]['product']);
    }

    /** @param array<int, ProductIdentityInterface> $identities */
    private function modifier(string $mode, array $identities, string $mappedSku): ErgonodeIdentity
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(1);
        $locator = $this->createStub(LocatorInterface::class);
        $locator->method('getProduct')->willReturn($product);
        $identityService = $this->createStub(ProductIdentityServiceInterface::class);
        $identityService->method('getIdentitiesByProductIds')->willReturn($identities);
        $modeProvider = $this->createStub(ProductIdentityModeProviderInterface::class);
        $modeProvider->method('getMode')->willReturn($mode);
        $identityAttribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $identityAttribute->method('getValuesByProductIds')->willReturn([1 => $mappedSku]);

        return new ErgonodeIdentity($locator, $identityService, $modeProvider, $identityAttribute);
    }
}
