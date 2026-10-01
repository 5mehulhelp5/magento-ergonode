<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Test\Integration\Model;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\ProductCategoryConsumer\Model\CategoryIdsResolver;
use Ergonode\ProductCategoryConsumer\Model\GraphQl\RemoteProductCategoryCodeLoader;
use Ergonode\ProductCategoryConsumer\Model\ProductCategoryImportSynchronizer;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class ProductCategoryAssignmentIntegrationTest extends TestCase
{
    #[DataFixture(CategoryFixture::class, as: 'category')]
    #[DataFixture(ProductFixture::class, as: 'product')]
    public function testIncompleteResponsePreservesAssignmentsAndValidEmptyResponseClearsThem(): void
    {
        $manager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $category = $fixtures->get('category');
        $product = $fixtures->get('product');
        self::assertInstanceOf(Category::class, $category);
        self::assertInstanceOf(Product::class, $product);
        $categoryId = (int)$category->getId();
        $productId = (int)$product->getId();
        $sku = (string)$product->getSku();

        $links = $manager->get(CategoryLinkManagementInterface::class);
        $links->assignProductToCategories($sku, [$categoryId]);
        self::assertSame([$categoryId], $this->categoryIds($productId));
        // Treat the initial assignment and import as separate requests with fresh category models.
        $categories = $manager->get(CategoryRepositoryInterface::class);
        self::assertInstanceOf(CategoryRepository::class, $categories);
        $categories->_resetState();

        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            ['product' => ['sku' => 'ERG-1', 'categoryList' => ['edges' => null, 'pageInfo' => null]]],
            ['product' => ['sku' => 'ERG-1', 'categoryList' => [
                'edges' => [],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ]]]
        );
        $synchronizer = new ProductCategoryImportSynchronizer(
            new RemoteProductCategoryCodeLoader($client),
            $manager->get(CategoryIdsResolver::class),
            $links
        );
        $source = new RemoteProduct('ERG-1', 'simple', 'template', false, [], []);

        try {
            $synchronizer->synchronize($productId, $sku, $source);
            self::fail('Incomplete category data must not replace the assignment.');
        } catch (LocalizedException) {
            self::assertSame([$categoryId], $this->categoryIds($productId));
        }

        $synchronizer->synchronize($productId, $sku, $source);
        self::assertSame([], $this->categoryIds($productId));
    }

    /** @return int[] */
    private function categoryIds(int $productId): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $ids = $connection->fetchCol(
            $connection->select()
                ->from($resource->getTableName('catalog_category_product'), ['category_id'])
                ->where('product_id = ?', $productId)
                ->order('category_id')
        );

        return array_map('intval', $ids);
    }
}
