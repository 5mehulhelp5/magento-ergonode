<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryPositionWriterInterface;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['name' => 'Sync order parent'], as: 'parent')]
#[DataFixture(CategoryFixture::class, ['parent_id' => '$parent.id$', 'position' => 1], as: 'first')]
#[DataFixture(CategoryFixture::class, ['parent_id' => '$parent.id$', 'position' => 2], as: 'second')]
#[DataFixture(CategoryFixture::class, ['parent_id' => '$parent.id$', 'position' => 3], as: 'third')]
class CategoryPositionWriterIntegrationTest extends TestCase
{
    public function testMovesToBeginningAndAfterAHigherIdUsingFreshPositions(): void
    {
        $manager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $parentId = (int)$fixtures->get('parent')->getId();
        $firstId = (int)$fixtures->get('first')->getId();
        $secondId = (int)$fixtures->get('second')->getId();
        $thirdId = (int)$fixtures->get('third')->getId();
        $writer = $manager->get(CategoryPositionWriterInterface::class);
        $writer->move($thirdId, $parentId, 0);
        self::assertSame([$thirdId, $firstId, $secondId], $this->children($parentId));
        // The previous sibling has a greater ID than the last child: do not clamp it.
        $writer->move($secondId, $parentId, $thirdId);
        self::assertSame([$thirdId, $secondId, $firstId], $this->children($parentId));
        $writer->move($thirdId, $parentId, $firstId);
        self::assertSame([$secondId, $firstId, $thirdId], $this->children($parentId));
    }

    public function testCachedSiblingPositionsMatchMagentoWhenPositionsAreDuplicated(): void
    {
        $manager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $parentId = (int)$fixtures->get('parent')->getId();
        $firstId = (int)$fixtures->get('first')->getId();
        $secondId = (int)$fixtures->get('second')->getId();
        $thirdId = (int)$fixtures->get('third')->getId();
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('catalog_category_entity');
        foreach ([$firstId => 2, $secondId => 2, $thirdId => 4] as $id => $position) {
            $connection->update($table, ['position' => $position], ['entity_id = ?' => $id]);
        }
        $provider = $manager->get(MagentoCategoryProvider::class);
        $provider->clearCache();
        $provider->getCategories(2);
        $manager->get(CategoryPositionWriterInterface::class)->move($secondId, $parentId, 0);
        $provider->markMoved(2, $secondId, $parentId, 1);
        self::assertSame([$secondId, $firstId, $thirdId], $this->children($parentId));
        $actual = $connection->fetchPairs(
            $connection->select()->from($table, ['entity_id', 'position'])->where('parent_id = ?', $parentId)
        );
        foreach ($actual as $id => $position) {
            self::assertSame((int)$position, $provider->getCategory((int)$id, 2)['position']);
        }
    }

    /** @return list<int> */
    private function children(int $parentId): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $select = $connection->select()->from($resource->getTableName('catalog_category_entity'), ['entity_id'])
            ->where('parent_id = ?', $parentId)->order(['position ASC', 'entity_id ASC']);

        return array_map('intval', $connection->fetchCol($select));
    }
}
