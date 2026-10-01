<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;
use Ergonode\Category\Api\CategoryLayoutValidatorInterface;
use Ergonode\CategoryPublisherAdminUi\Plugin\CategoryLayoutSaverPlugin;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationDetails;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Api\CategoryTreeSnapshotUpdaterInterface;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryRemoteIdentityResolver;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreePayloadBuilder;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreePublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\PendingCategoryPublisher;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryTreePublisherTest extends TestCase
{
    #[DataProvider('layoutChanges')]
    public function testOnlyActualHierarchyChangesRequireManualRest(
        array $changes,
        bool $expected,
        array $storedChanges = []
    ): void {
        $snapshot = [
            'root' => ['code' => 'root', 'parent_code' => null, 'sort_order' => 1822],
            'a' => ['code' => 'a', 'parent_code' => 'root', 'sort_order' => 1823],
            'b' => ['code' => 'b', 'parent_code' => 'root', 'sort_order' => 1824],
        ];
        $items = [
            ['code' => 'root', 'parent_code' => null, 'sort_order' => 40, 'magento_category_id' => 3],
            ['code' => 'a', 'parent_code' => 'root', 'sort_order' => 1, 'magento_category_id' => null],
            ['code' => 'b', 'parent_code' => 'root', 'sort_order' => 2, 'magento_category_id' => 4],
        ];
        $items[1] = array_replace($items[1], $changes);
        $snapshot['a'] = array_replace($snapshot['a'], $storedChanges);
        $cache = $this->createStub(CategoryCacheProvider::class);
        $cache->method('getRowsByCode')->willReturn($snapshot);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::never())->method('getTree');
        $gateway->expects(self::never())->method('updateTree');
        $publisher = new CategoryTreePublisher(
            $cache,
            $this->createStub(CategoryTreeQuery::class),
            $gateway,
            new CategoryTreePayloadBuilder(),
            $this->createStub(CategoryTreeSnapshotUpdaterInterface::class),
            $this->createStub(CategoryRemoteIdentityResolver::class),
            $this->createStub(PendingCategoryPublisher::class),
            $this->createStub(CategoryPublicationDetails::class)
        );

        self::assertSame($expected, $publisher->requiresManualWrite(7, $items));
    }

    public static function layoutChanges(): array
    {
        return [
            'unmapping with Magento sibling positions' => [[], false],
            'mapping with Magento sibling positions' => [['magento_category_id' => 22], false],
            'equivalent sibling order with different numeric positions' => [['sort_order' => 0], false],
            'saved sibling override' => [['sort_order' => 3], false, ['effective_sort_order' => 1825]],
            'saved parent override' => [['parent_code' => 'b'], false, ['effective_parent_code' => 'b']],
            'changed sibling order' => [['sort_order' => 3], true],
            'changed parent' => [['parent_code' => null], true],
            'pending creation' => [['extension_data' => ['to_ergonode' => ['pending_create' => true]]], true],
            'prepared remote category' => [['extension_data' => ['to_ergonode' => ['remote_prepared' => true]]], true],
        ];
    }

    public function testDisconnectingAllCategoriesWithoutRemoteIdsSavesLocallyOnRepeatedSaves(): void
    {
        $snapshot = [];
        $items = [];
        for ($index = 0; $index < 2500; $index++) {
            $code = 'category-' . $index;
            $parent = $index > 0 ? 'category-0' : null;
            $snapshot[$code] = [
                'code' => $code,
                'parent_code' => $parent,
                'sort_order' => 1800 + $index,
                'ergonode_category_id' => null,
                'magento_category_id' => 100 + $index,
            ];
            $items[] = [
                'code' => $code,
                'parent_code' => $parent,
                'sort_order' => $index,
                'magento_category_id' => null,
                'extension_data' => [],
            ];
        }
        $cache = $this->createStub(CategoryCacheProvider::class);
        $cache->method('getRowsByCode')->willReturnCallback(static fn (): array => $snapshot);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::never())->method('getTree');
        $gateway->expects(self::never())->method('updateTree');
        $pending = $this->createMock(PendingCategoryPublisher::class);
        $pending->expects(self::never())->method('publish');
        $resolver = $this->createMock(CategoryRemoteIdentityResolver::class);
        $resolver->expects(self::never())->method('resolve');
        $publisher = new CategoryTreePublisher(
            $cache,
            $this->createStub(CategoryTreeQuery::class),
            $gateway,
            new CategoryTreePayloadBuilder(),
            $this->createStub(CategoryTreeSnapshotUpdaterInterface::class),
            $resolver,
            $pending,
            $this->createStub(CategoryPublicationDetails::class)
        );
        $visibility = [['source' => 'ergo', 'identifier' => 'category-1', 'active' => false]];
        $saves = 0;
        $proceed = static function (
            int $treeId,
            array $payload,
            array $actualVisibility
        ) use (
            $items,
            $visibility,
            &$saves
        ): array {
            self::assertSame(3, $treeId);
            self::assertSame($items, $payload);
            self::assertSame($visibility, $actualVisibility);
            $saves++;

            return ['updated' => 2500, 'unchanged' => 0];
        };
        $plugin = new CategoryLayoutSaverPlugin(
            $publisher,
            $this->createStub(CategoryLayoutValidatorInterface::class)
        );
        for ($attempt = 0; $attempt < 2; $attempt++) {
            self::assertSame(['updated' => 2500, 'unchanged' => 0], $plugin->aroundSave(
                $this->createStub(CategoryLayoutSaver::class),
                $proceed,
                3,
                $items,
                $visibility
            ));
        }
        self::assertSame(2, $saves);
    }

    #[DataProvider("snapshotFailures")]
    public function testUpdatesSnapshotFromTheSuccessfullyPublishedLayout(bool $fails): void
    {
        $items = [[
            'code' => 'chairs',
            'parent_code' => null,
            'sort_order' => 0,
        ]];
        $categoryCacheProvider = $this->createStub(CategoryCacheProvider::class);
        $categoryTreeQuery = $this->createMock(CategoryTreeQuery::class);
        $categoryTreeQuery->expects(self::once())->method('getById')->with(7)->willReturn([
            'tree_code' => 'main',
        ]);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::once())->method('getTree')->with('main')->willReturn([
            'id' => 'tree-id',
            'name' => ['pl_PL' => 'Main'],
            'categories' => [['category_id' => 'category-id']],
        ]);
        $gateway->expects(self::once())->method('updateTree')->with('tree-id', [
            'name' => ['pl_PL' => 'Main'],
            'categories' => [['category_id' => 'category-id', 'children' => []]],
        ]);
        $payloadBuilder = $this->createMock(CategoryTreePayloadBuilder::class);
        $payloadBuilder->expects(self::once())->method('build')->with(
            $items,
            ['chairs' => 'category-id']
        )->willReturn([['category_id' => 'category-id', 'children' => []]]);
        $layout = [['code' => 'chairs', 'parent_code' => null]];
        $payloadBuilder->expects(self::once())->method('snapshotLayout')->with(
            [['category_id' => 'category-id', 'children' => []]],
            ['chairs' => 'category-id']
        )->willReturn($layout);
        $snapshotUpdater = $this->createMock(CategoryTreeSnapshotUpdaterInterface::class);
        $confirmed = ['chairs' => ['code' => 'chairs', 'name' => [['language' => 'pl_PL', 'value' => 'Krzesła']]]];
        $snapshotExpectation = $snapshotUpdater->expects(self::once())->method('update')->with(7, $layout, $confirmed);
        $details = $this->createMock(CategoryPublicationDetails::class);
        $details->expects(self::once())->method('get')->with(7)->willReturn($confirmed);
        $details->expects(self::exactly($fails ? 0 : 1))->method('clear')->with(7, ['chairs']);
        if ($fails) {
            $snapshotExpectation->willThrowException(new LocalizedException(__('Snapshot failed')));
            $this->expectException(LocalizedException::class);
            $this->expectExceptionMessage('Magento could not refresh its snapshot');
        }
        $identityResolver = $this->createMock(CategoryRemoteIdentityResolver::class);
        $identityResolver->expects(self::once())->method('resolve')->with(7, $items)->willReturn([
            'chairs' => 'category-id',
        ]);
        $pendingCategoryPublisher = $this->createMock(PendingCategoryPublisher::class);
        $pendingCategoryPublisher->expects(self::once())->method('publish')->with(7, $items);

        (new CategoryTreePublisher(
            $categoryCacheProvider,
            $categoryTreeQuery,
            $gateway,
            $payloadBuilder,
            $snapshotUpdater,
            $identityResolver,
            $pendingCategoryPublisher,
            $details
        ))->publish(7, $items);
    }

    public static function snapshotFailures(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('rejectedWrites')]
    public function testSnapshotIsNotChangedWhenRemoteTreeCannotBeWritten(bool $unknownCategory): void
    {
        $items = [['code' => 'chairs', 'parent_code' => null, 'sort_order' => 0]];
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn(['tree_code' => 'main']);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::once())->method('getTree')->willReturn([
            'id' => 'tree-id',
            'categories' => [['category_id' => $unknownCategory ? 'unknown-id' : 'chairs-id']],
        ]);
        if ($unknownCategory) {
            $gateway->expects(self::never())->method('updateTree');
        } else {
            $gateway->expects(self::once())->method('updateTree')
                ->willThrowException(new LocalizedException(__('Write denied')));
        }
        $updater = $this->createMock(CategoryTreeSnapshotUpdaterInterface::class);
        $updater->expects(self::never())->method('update');
        $resolver = $this->createStub(CategoryRemoteIdentityResolver::class);
        $resolver->method('resolve')->willReturn(['chairs' => 'chairs-id']);
        $pending = $this->createMock(PendingCategoryPublisher::class);
        $pending->expects(self::once())->method('publish')->with(7, $items);
        $this->expectException(LocalizedException::class);

        (new CategoryTreePublisher(
            $this->createStub(CategoryCacheProvider::class),
            $treeQuery,
            $gateway,
            new CategoryTreePayloadBuilder(),
            $updater,
            $resolver,
            $pending,
            $this->createStub(CategoryPublicationDetails::class)
        ))->publish(7, $items);
    }

    public static function rejectedWrites(): array
    {
        return [[true], [false]];
    }
}
