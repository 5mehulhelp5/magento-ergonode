<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributePublisher\Test\Unit\Model\Publisher;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttributePublisher\Model\Publisher\CategoryReferencePublisherValueResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryReferencePublisherValueResolverTest extends TestCase
{
    public function testResolvesMagentoCategoryIdToErgonodeCategoryCode(): void
    {
        $config = $this->configuredAttribute(true);
        $mappingProvider = $this->createMock(CategoryMappingProviderInterface::class);
        $mappingProvider->expects(self::once())
            ->method('getCategoryCodesByMagentoIds')
            ->with([17])
            ->willReturn([17 => 'chairs']);
        $resolver = new CategoryReferencePublisherValueResolver($config, $mappingProvider);
        $mapping = ['magento_attribute_code' => 'default_category', 'ergonode_type' => 'text'];

        self::assertTrue($resolver->supports($mapping));
        self::assertSame('chairs', $resolver->resolve('17', $mapping, 'product "SKU-1"'));
    }

    #[DataProvider('missingValues')]
    public function testMagentoRequiredFlagDoesNotBlockMissingOutgoingValue(?string $value): void
    {
        $mappingProvider = $this->createMock(CategoryMappingProviderInterface::class);
        $mappingProvider->expects(self::never())->method('getCategoryCodesByMagentoIds');
        $resolver = new CategoryReferencePublisherValueResolver(
            $this->configuredAttribute(true),
            $mappingProvider
        );

        self::assertNull($resolver->resolve(
            $value,
            ['magento_attribute_code' => 'default_category', 'ergonode_type' => 'text'],
            'product "SKU-1" attribute "default_category"'
        ));
    }

    /** @return array<string, array{?string}> */
    public static function missingValues(): array
    {
        return ['null' => [null], 'empty string' => ['']];
    }

    public function testOptionalMissingValueIsOmitted(): void
    {
        $resolver = new CategoryReferencePublisherValueResolver(
            $this->configuredAttribute(false),
            $this->createStub(CategoryMappingProviderInterface::class)
        );

        self::assertNull($resolver->resolve(
            null,
            ['magento_attribute_code' => 'default_category', 'ergonode_type' => 'text'],
            'product "SKU-1"'
        ));
    }

    private function configuredAttribute(bool $required): CategoryReferenceAttributeConfigInterface
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturnCallback(
            static fn (string $code): bool => $code === 'default_category'
        );
        $config->method('isRequired')->willReturn($required);

        return $config;
    }
}
