<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Integration\Model\ResourceModel;

use Ergonode\Product\Api\ProductCatalogInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationResultWriterInterface;
use Ergonode\ProductPublisher\Model\ResourceModel\ProductPublicationResultReader;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class ProductPublicationResultIntegrationTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['sku' => 'publication-result-one'], as: 'product')]
    #[DataFixture(ProductFixture::class, ['sku' => 'publication-result-two'], as: 'other')]
    public function testLatestResultSurvivesReloadWithoutChangingGridSelectionOrMappings(): void
    {
        $manager = Bootstrap::getObjectManager();
        $product = DataFixtureStorageManager::getStorage()->get('product');
        $other = DataFixtureStorageManager::getStorage()->get('other');
        self::assertInstanceOf(Product::class, $product);
        self::assertInstanceOf(Product::class, $other);
        $productId = (int)$product->getId();
        $otherId = (int)$other->getId();
        $writer = $manager->get(ProductPublicationResultWriterInterface::class);
        $catalog = $manager->get(ProductCatalogInterface::class);
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_product_publication_result');
        $message = 'Attribute: sku. Language: en_GB. Code: VALIDATION_ERROR. <script>alert(1)</script>';
        $time = $writer->save([['product_id' => $productId, 'status' => 'failed', 'message' => $message]]);
        $page = $manager->create(ProductCatalogInterface::class)->getPage('publication-result-', 1, 20);
        $byId = array_column($page['items'], null, 'product_id');
        self::assertSame(2, $page['total']);
        $stored = $connection->fetchRow($connection->select()->from($table)->where('product_id = ?', $productId));
        self::assertSame('failed', $stored['status']);
        self::assertSame($message, $stored['message']);
        self::assertSame($time, $stored['recorded_at']);
        self::assertArrayNotHasKey('publication_status', $byId[$productId]);
        self::assertSame('', $byId[$productId]['ergonode_sku']);

        $reader = $manager->get(ProductPublicationResultReader::class);
        self::assertSame([], $reader->getUnconfirmedTimes([$productId, $otherId]));
        $unconfirmedAt = $writer->save([[
            'product_id' => $productId,
            'status' => 'unconfirmed',
            'message' => 'Confirm remote creation.',
        ]]);
        self::assertSame([$productId => $unconfirmedAt], $reader->getUnconfirmedTimes([$productId, $otherId]));

        $writer->save([['product_id' => $productId, 'status' => 'success', 'message' => '']]);
        self::assertSame([], $reader->getUnconfirmedTimes([$productId]));
        $page = $catalog->getPage('', 1, 20, ['product_id' => (string)$productId]);
        $stored = $connection->fetchRow($connection->select()->from($table)->where('product_id = ?', $productId));
        self::assertSame('success', $stored['status']);
        self::assertSame('', $stored['message']);
        self::assertSame([$productId, $otherId], $catalog->getIds('publication-result-', [], [], true));
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_product_publication_result');
        self::assertSame(1, (int)$connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])->where('product_id = ?', $productId)
        ));
        $connection->delete($resource->getTableName('catalog_product_entity'), ['entity_id = ?' => $productId]);
        self::assertSame(0, (int)$connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])->where('product_id = ?', $productId)
        ));
        $writer->save([['product_id' => $productId, 'status' => 'failed', 'message' => 'Deleted product.']]);
        self::assertSame(0, (int)$connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])->where('product_id = ?', $productId)
        ));
    }
}
