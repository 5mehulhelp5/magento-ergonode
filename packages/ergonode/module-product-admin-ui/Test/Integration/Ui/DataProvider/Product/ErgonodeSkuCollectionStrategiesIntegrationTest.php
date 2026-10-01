<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Integration\Ui\DataProvider\Product;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductAdminUi\Ui\DataProvider\Product\AddErgonodeSkuFieldToCollection;
use Ergonode\ProductAdminUi\Ui\DataProvider\Product\AddErgonodeSkuFilterToCollection;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class ErgonodeSkuCollectionStrategiesIntegrationTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['sku' => 'mapped-product'], as: 'mapped_product')]
    #[DataFixture(ProductFixture::class, ['sku' => 'unmapped-product'], as: 'unmapped_product')]
    public function testGridFieldAndFilterUseSharedIdentityMapping(): void
    {
        $storage = DataFixtureStorageManager::getStorage();
        $mappedProduct = $storage->get('mapped_product');
        $unmappedProduct = $storage->get('unmapped_product');
        self::assertInstanceOf(Product::class, $mappedProduct);
        self::assertInstanceOf(Product::class, $unmappedProduct);

        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(ProductIdentityServiceInterface::class)->bind(
            (int)$mappedProduct->getId(),
            'ERG-GRID-001',
            ProductIdentityInterface::MODE_ASSIGNED
        );
        $fieldStrategy = $objectManager->get(AddErgonodeSkuFieldToCollection::class);
        $filterStrategy = $objectManager->get(AddErgonodeSkuFilterToCollection::class);

        $unfiltered = $this->collection([(int)$mappedProduct->getId(), (int)$unmappedProduct->getId()]);
        $fieldStrategy->addField($unfiltered, 'ergonode_sku');
        self::assertCount(2, $unfiltered);
        self::assertSame('ERG-GRID-001', $unfiltered->getItemById($mappedProduct->getId())?->getData('ergonode_sku'));
        self::assertNull($unfiltered->getItemById($unmappedProduct->getId())?->getData('ergonode_sku'));

        $filtered = $this->collection([(int)$mappedProduct->getId(), (int)$unmappedProduct->getId()]);
        $fieldStrategy->addField($filtered, 'ergonode_sku');
        $filterStrategy->addFilter($filtered, 'ergonode_sku', ['like' => '%GRID-001%']);
        self::assertSame([(int)$mappedProduct->getId()], array_map('intval', $filtered->getAllIds()));
    }

    /** @param int[] $productIds */
    private function collection(array $productIds): Collection
    {
        $collection = Bootstrap::getObjectManager()->get(CollectionFactory::class)->create();
        $collection->addIdFilter($productIds);

        return $collection;
    }
}
