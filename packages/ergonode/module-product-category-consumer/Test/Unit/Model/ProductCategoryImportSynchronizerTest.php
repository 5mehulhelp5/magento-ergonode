<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Test\Unit\Model;

use Ergonode\ProductCategoryConsumer\Model\CategoryIdsResolver;
use Ergonode\ProductCategoryConsumer\Model\GraphQl\RemoteProductCategoryCodeLoader;
use Ergonode\ProductCategoryConsumer\Model\ProductCategoryImportSynchronizer;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use PHPUnit\Framework\TestCase;

class ProductCategoryImportSynchronizerTest extends TestCase
{
    public function testAssignsResolvedCategoriesUsingMagentoSku(): void
    {
        $source = new RemoteProduct('ERG-1', 'simple', 'template', false, [], []);
        $loader = $this->createMock(RemoteProductCategoryCodeLoader::class);
        $loader->expects(self::once())->method('load')->with('ERG-1')->willReturn(['chairs']);
        $resolver = $this->createMock(CategoryIdsResolver::class);
        $resolver->expects(self::once())->method('resolve')->with(['chairs'])->willReturn([42]);
        $links = $this->createMock(CategoryLinkManagementInterface::class);
        $links->expects(self::once())->method('assignProductToCategories')->with('MAGENTO-1', [42]);

        (new ProductCategoryImportSynchronizer($loader, $resolver, $links))->synchronize(
            17,
            'MAGENTO-1',
            $source
        );
    }
}
