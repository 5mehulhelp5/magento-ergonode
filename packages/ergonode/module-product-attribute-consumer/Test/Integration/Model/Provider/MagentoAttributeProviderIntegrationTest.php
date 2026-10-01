<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Provider;

use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true)]
class MagentoAttributeProviderIntegrationTest extends TestCase
{
    public function testProductStatusRemainsASelect(): void
    {
        $attribute = Bootstrap::getObjectManager()
            ->get(MagentoAttributeProvider::class)
            ->getAttribute('status', true);

        self::assertNotNull($attribute);
        self::assertSame('select', $attribute['type']);
    }

    #[Config('ergonode_products/attributes/status', 'mapping')]
    #[Config('ergonode_products/attributes/visibility', 'mapping')]
    #[Config('ergonode_products/attributes/url_key', 'mapping')]
    public function testConfiguredProductMappingsAreRequiredAndActive(): void
    {
        $provider = Bootstrap::getObjectManager()->get(MagentoAttributeProvider::class);

        foreach (['status', 'visibility', 'url_key'] as $attributeCode) {
            $attribute = $provider->getAttribute($attributeCode);

            self::assertNotNull($attribute);
            self::assertTrue($attribute['required'], $attributeCode);
            self::assertTrue($attribute['active'], $attributeCode);
        }
    }
}
