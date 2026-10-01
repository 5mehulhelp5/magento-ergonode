<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Source;

use ArrayIterator;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductPublisher\Api\ProductTemplateCodeProviderInterface;
use Ergonode\ProductPublisher\Model\Source\EmptyProductAttributePublicationSource;
use Ergonode\ProductPublisher\Model\Source\MagentoProductSourceProvider;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

class MagentoProductSourceProviderTest extends TestCase
{
    public function testBaseSourceLoadsNoAttributeValuesOrLocalizedCopies(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn('MINIMAL-1');
        $product->method('getAttributeSetId')->willReturn(4);
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addAttributeToSelect')->with([])->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$product]));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($collection);
        $templates = $this->createStub(ProductTemplateCodeProviderInterface::class);
        $templates->method('getTemplateCodesByAttributeSetIds')->willReturn([4 => 'default']);
        $languages = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $languages->expects(self::never())->method('getLanguageStoreMap');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $source = new MagentoProductSourceProvider(
            $factory,
            new EmptyProductAttributePublicationSource(),
            $templates,
            $languages,
            $resource
        );

        $result = $source->load();

        self::assertSame(['MINIMAL-1' => $product], $result->getProducts());
        self::assertSame([], $result->getStoreProducts());
        self::assertSame([], $result->getAttributeMappings());
        self::assertSame([4 => 'default'], $result->getTemplateCodes());
    }
}
