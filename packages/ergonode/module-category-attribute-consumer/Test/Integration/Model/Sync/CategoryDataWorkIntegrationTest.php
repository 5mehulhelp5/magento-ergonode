<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\Category\Model\Import\CategoryStreamPageReader;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeRegistryRefresherInterface;
use Ergonode\CategoryAttributeConsumer\Model\Import\CategoryEntityLoader;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryEntitySynchronizer;
use Ergonode\CategoryConsumer\Api\CategoryCreationDataProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use Ergonode\Core\Model\Import\CursorStorage;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class CategoryDataWorkIntegrationTest extends TestCase
{
    #[Config('ergonode_category_attributes/synchronization/status', '1')]
    #[Config('ergonode_category_attributes/synchronization/is_active_mode', 'manual')]
    #[Config('ergonode_category_attributes/synchronization/include_in_menu_mode', 'manual')]
    #[Config('ergonode_category_attributes/synchronization/is_active_default', '1')]
    #[Config('ergonode_category_attributes/synchronization/include_in_menu_default', '0')]
    #[Config('ergonode_categories/synchronization/name_mode', 'manual')]
    public function testReconcilesDefinitionsButSkipsCategoryFetchAndEmptyStreamWithoutAdvancingCursor(): void
    {
        $manager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $manager);
        $registry = $this->createMock(CategoryAttributeRegistryRefresherInterface::class);
        $registry->expects(self::exactly(3))->method('refresh')->willReturn(['imported' => 0]);
        $manager->addSharedInstance($registry, CategoryAttributeRegistryRefresherInterface::class, true);
        $loader = $this->createMock(CategoryEntityLoader::class);
        $loader->expects(self::never())->method('load');
        $manager->addSharedInstance($loader, CategoryEntityLoader::class);
        $synchronizer = $manager->get(CategoryEntitySynchronizerInterface::class);
        self::assertInstanceOf(CategoryEntitySynchronizer::class, $synchronizer);
        self::assertInstanceOf(CategoryDataWorkProviderInterface::class, $synchronizer);
        self::assertFalse($synchronizer->hasWork());
        self::assertSame([
            'values' => ['include_in_menu' => 0, 'is_active' => 1],
            'entity' => null,
        ], $manager->get(CategoryCreationDataProviderInterface::class)->get('no-remote-fetch-needed'));

        $reader = $this->createMock(CategoryStreamPageReader::class);
        $reader->expects(self::never())->method('readAll');
        $cursor = $manager->get(CursorStorage::class);
        $cursor->save(CategoryEntityStreamImporter::PROCESS_CODE, 'cursor-before');
        $importer = $manager->create(CategoryEntityStreamImporter::class, [
            'pageReader' => $reader,
            'entityLoader' => $loader,
        ]);

        $result = $importer->execute();
        self::assertTrue($result['skipped']);
        self::assertSame('cursor-before', $result['cursor']);
        self::assertSame('cursor-before', $cursor->get(CategoryEntityStreamImporter::PROCESS_CODE)['cursor']);
        $forced = $importer->execute(true);
        self::assertTrue($forced['skipped']);
        self::assertNull($forced['cursor']);
        self::assertNull($cursor->get(CategoryEntityStreamImporter::PROCESS_CODE)['cursor'] ?? null);
    }

    #[Config('ergonode_categories/synchronization/name_mode', 'source')]
    public function testConfiguredNameUpdatesKeepTheDataPhaseEnabled(): void
    {
        $synchronizer = Bootstrap::getObjectManager()->get(CategoryEntitySynchronizerInterface::class);
        self::assertInstanceOf(CategoryDataWorkProviderInterface::class, $synchronizer);
        self::assertTrue($synchronizer->hasWork());
    }
}
