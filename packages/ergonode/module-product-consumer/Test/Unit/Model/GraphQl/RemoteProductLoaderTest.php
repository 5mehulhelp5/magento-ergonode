<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\GraphQl;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeSourcePreparationInterface;

use Ergonode\Attribute\Api\AttributeValueNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Api\ProductAttributeCodeProviderInterface;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\GraphQl\ProductAttributeCodeProviderPool;
use Ergonode\ProductConsumer\Model\GraphQl\ProductQueries;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductAttributeFactory;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteProductLoaderTest extends TestCase
{
    /** @return array<string, array{bool, string[]}> */
    public static function optionalAttributeProvider(): array
    {
        return [
            'enabled' => [true, ['magento_product_type', 'gallery', 'color']],
            'disabled' => [false, ['magento_product_type', 'color']],
        ];
    }

    public function testCurrentVersionReusesCompleteStreamSnapshot(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::once())->method('query')->with(ProductQueries::CURRENT_PRODUCT, ['sku' => 'SKU'])
            ->willReturn(['product' => [
                '__typename' => 'SimpleProduct',
                'sku' => 'SKU',
                'createdAt' => '2026-08-21T10:00:00+00:00',
                'editedAt' => null,
            ]]);
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn([]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL']);
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('getTypeAttributeCode')->willReturn('magento_product_type');
        $loader = new RemoteProductLoader(
            $this->createMock(ProductAttributeSourcePreparationInterface::class),
            $client,
            $mappingProvider,
            $languages,
            $this->createStub(ErgonodeAttributeTypeResolverInterface::class),
            new RemoteProductAttributeFactory($this->createStub(AttributeValueNormalizerInterface::class)),
            $config,
            new ProductAttributeCodeProviderPool()
        );
        $snapshot = [
            '__typename' => 'SimpleProduct',
            'sku' => 'SKU',
            'createdAt' => '2026-08-21T10:00:00+00:00',
            'editedAt' => null,
            'template' => ['code' => 'default'],
            'status' => [['language' => 'pl_PL', 'value' => ['code' => 'active']]],
            'attributeList' => ['pageInfo' => ['hasNextPage' => false], 'edges' => []],
            'isVariant' => false,
        ];

        $product = $loader->loadCurrent('SKU', $snapshot);

        self::assertNotNull($product);
        self::assertSame('SKU', $product->sku);
        self::assertSame('simple', $product->type);
        self::assertSame(['pl_PL' => 'active'], $product->statuses);
    }

    /** @param string[] $expectedAttributeCodes */
    #[DataProvider('optionalAttributeProvider')]
    public function testRequestsCodesContributedByOptionalExtensions(
        bool $enabled,
        array $expectedAttributeCodes
    ): void {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::once())
            ->method('query')
            ->with(ProductQueries::PRODUCT, [
                'sku' => 'SKU',
                'languages' => ['pl_PL'],
                'attributeCodes' => $expectedAttributeCodes,
            ])
            ->willReturn(['product' => [
                '__typename' => 'SimpleProduct',
                'sku' => 'SKU',
                'template' => ['code' => 'default'],
                'status' => [],
                'attributeList' => ['pageInfo' => ['hasNextPage' => false], 'edges' => []],
                'isVariant' => false,
            ]]);
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn([
            ['ergonode_attribute_code' => 'color'],
        ]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL']);
        $config = $this->createStub(ProductImportConfig::class);
        $config->method('getTypeAttributeCode')->willReturn('magento_product_type');
        $provider = $this->createStub(ProductAttributeCodeProviderInterface::class);
        $provider->method('getAttributeCodes')->willReturn($enabled ? ['gallery'] : []);

        $product = (new RemoteProductLoader(
            $this->createMock(ProductAttributeSourcePreparationInterface::class),
            $client,
            $mappingProvider,
            $languages,
            $this->createStub(ErgonodeAttributeTypeResolverInterface::class),
            new RemoteProductAttributeFactory($this->createStub(AttributeValueNormalizerInterface::class)),
            $config,
            new ProductAttributeCodeProviderPool([$provider])
        ))->load('SKU');

        self::assertNotNull($product);
        self::assertSame('SKU', $product->sku);
    }
}
