<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\GraphQl;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Api\ProductAttributeCodeProviderInterface;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\GraphQl\ProductAttributeCodeProviderPool;
use Ergonode\ProductConsumer\Model\GraphQl\ProductQueries;
use Ergonode\ProductConsumer\Model\GraphQl\ProductStreamPageReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductStreamPageReaderTest extends TestCase
{
    /** @return array<string, array{bool, string[]}> */
    public static function optionalAttributeProvider(): array
    {
        return [
            'enabled' => [true, ['magento_product_type', 'gallery', 'color']],
            'disabled' => [false, ['magento_product_type', 'color']],
        ];
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
            ->with(ProductQueries::PRODUCT_STREAM, [
                'first' => 20,
                'after' => null,
                'languages' => ['pl_PL'],
                'attributeCodes' => $expectedAttributeCodes,
            ])
            ->willReturn([
                'productStream' => [
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    'edges' => [],
                ],
            ]);
        $retrier = $this->createMock(PageQueryRetrierInterface::class);
        $retrier->expects(self::once())
            ->method('query')
            ->willReturnCallback(static function (array $sizes, int $requested, callable $query): array {
                self::assertSame([50, 25, 10, 5, 1], $sizes);
                $result = $query($requested);
                $result['_page_size'] = $requested;

                return $result;
            });
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

        $result = (new ProductStreamPageReader(
            $client,
            $retrier,
            $mappingProvider,
            $languages,
            $config,
            new ProductAttributeCodeProviderPool([$provider])
        ))->readChanged(null, 20);

        self::assertSame([], $result['items']);
    }
}
