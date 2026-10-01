<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeConsumer\Test\Unit\Plugin;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttributeConsumer\Plugin\ProductAttributeValueMapperPlugin;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProductAttributeValueMapperPluginTest extends TestCase
{
    public function testRejectsMissingMappingForRequiredConfiguredAttribute(): void
    {
        $config = $this->requiredConfig();
        $mappings = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappings->method('getMappings')->willReturn([]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('has no complete Ergonode mapping');

        (new ProductAttributeValueMapperPlugin($config, $mappings))->afterMapSpecial(
            $this->createStub(ProductAttributeValueMapper::class),
            ['values' => [], 'clear' => []]
        );
    }

    public function testAcceptsRequiredAdminValueWhenMappingExists(): void
    {
        $config = $this->requiredConfig();
        $mappings = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappings->method('getMappings')->willReturn([['magento_attribute_code' => 'default_category']]);
        $result = ['values' => ['default_category' => [0 => 42]], 'clear' => []];

        self::assertSame(
            $result,
            (new ProductAttributeValueMapperPlugin($config, $mappings))->afterMapSpecial(
                $this->createStub(ProductAttributeValueMapper::class),
                $result
            )
        );
    }

    public function testDoesNotRequireCategoryOutsideSelectedAttributeSet(): void
    {
        $mappings = $this->createMock(ProductAttributeMappingProviderInterface::class);
        $mappings->expects(self::never())->method('getMappings');
        $result = ['values' => ['name' => [0 => 'Chair']], 'clear' => []];

        self::assertSame($result, (new ProductAttributeValueMapperPlugin($this->requiredConfig(), $mappings))->afterMap(
            $this->createStub(ProductAttributeValueMapper::class),
            $result,
            [],
            ['name']
        ));
    }

    public function testStillRequiresAdminValueWhenCategoryIsSelected(): void
    {
        $mappings = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappings->method('getMappings')->willReturn([['magento_attribute_code' => 'default_category']]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('has no value for the admin store');

        (new ProductAttributeValueMapperPlugin($this->requiredConfig(), $mappings))->afterMap(
            $this->createStub(ProductAttributeValueMapper::class),
            ['values' => [], 'clear' => ['default_category' => [0]]],
            [],
            ['default_category']
        );
    }

    private function requiredConfig(): CategoryReferenceAttributeConfigInterface
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('getAttributeCode')->willReturn('default_category');
        $config->method('isRequired')->willReturn(true);
        $config->method('isConfigured')->willReturnCallback(
            static fn (string $code): bool => $code === 'default_category'
        );

        return $config;
    }
}
