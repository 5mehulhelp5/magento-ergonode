<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Integration;

use Ergonode\Category\Api\CategoryCreationContextProviderInterface;
use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Ergonode\Category\Api\CategoryLayoutSaverInterface;
use Ergonode\Category\Api\CategoryRemoteIdentityProviderInterface;
use Ergonode\Category\Api\CategoryRemoteIdentityWriterInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCodeGenerator;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryFormPublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryRemoteIdentityResolver;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true), DataFixture(CategoryFixture::class, ['name' => 'Zero'], as: 'category')]
class CategoryCodePublicationIntegrationTest extends TestCase
{
    #[DataProvider('codes')]
    public function testProvidersPreserveIdentityAcrossPendingAndPublishedForm(string $code, bool $published): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $category = DataFixtureStorageManager::getStorage()->get('category');
        self::assertNotNull($category);
        $categoryId = (int)$category->getId();
        $stores = $objectManager->get(StoreManagerInterface::class);
        $rootId = (int)$stores->getGroup((int)$stores->getDefaultStoreView()->getStoreGroupId())->getRootCategoryId();
        $treeId = $objectManager->get(CategoryTreeRepository::class)->save([
            'is_active' => true,
            'tree_code' => 'code-publication-test',
            'root_category_id' => $rootId,
        ]);
        $writer = $objectManager->get(CategoryRemoteIdentityWriterInterface::class);
        $writer->saveRemoteIdentities($treeId, [[
            'code' => $code,
            'remote_id' => 'remote-code-id',
            'manual_parent_code' => null,
            'manual_sort_order' => 1,
            'magento_category_id' => $categoryId,
        ]]);
        if ($published) {
            $objectManager->get(CategorySnapshotWriter::class)->saveCategories($treeId, [[
                'code' => $code,
                'parent_code' => null,
                'labels' => ['en_US' => 'Zero'],
                'sort_order' => 1,
                'raw' => ['code' => $code],
                'hash' => hash('sha256', $code),
            ]]);
        }

        $provider = $objectManager->get(CategoryRemoteIdentityProviderInterface::class);
        self::assertSame([$code => 'remote-code-id'], $provider->getIdsByCode($treeId, ['', ' ', $code]));
        self::assertSame([], $provider->getIdsByCode($treeId, ['', ' ']));
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::never())->method('getCategoryIds');
        self::assertSame([$code => 'remote-code-id'], (new CategoryRemoteIdentityResolver(
            $provider,
            $writer,
            $gateway
        ))->resolve($treeId, [['code' => $code]]));

        $formContext = $objectManager->get(CategoryFormContextProviderInterface::class)
            ->getForMagentoCategory($categoryId);
        self::assertNotNull($formContext);
        self::assertSame($published ? $code : null, $formContext['ergonode_category_code']);
        $contextProvider = $objectManager->get(CategoryCreationContextProviderInterface::class);
        $context = $contextProvider->getForMagentoCategory($categoryId);
        self::assertNotNull($context);
        self::assertSame($code, $context['pending_code']);

        $batch = $this->createMock(CategoryBatchPublisher::class);
        $saver = $this->createMock(CategoryLayoutSaverInterface::class);
        if ($published) {
            $batch->expects(self::never())->method('publish');
            $saver->expects(self::never())->method('save');
        } else {
            $batch->expects(self::once())->method('publish')->with(
                $treeId,
                self::callback(static fn (array $items): bool => count($items) === 1 && $items[0]['code'] === $code),
                [$code]
            )->willReturn([['code' => $code, 'status' => 'existing', 'remote_id' => 'remote-code-id']]);
            $saver->expects(self::once())->method('save')->with(
                $treeId,
                self::callback(static fn (array $items): bool => count($items) === 1
                    && $items[0]['code'] === $code
                    && $items[0]['ergonode_category_id'] === 'remote-code-id'
                    && $items[0]['extension_data']['to_ergonode']['remote_prepared'] === true)
            )->willReturn(['updated' => 1, 'unchanged' => 0, 'attribute_values' => 0]);
        }
        self::assertSame($code, (new CategoryFormPublisher(
            $contextProvider,
            $batch,
            $saver,
            new CategoryCodeGenerator()
        ))->publish($categoryId));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function codes(): iterable
    {
        foreach (['0', '001', 'chairs'] as $code) {
            yield $code . '-pending' => [$code, false];
            yield $code . '-published' => [$code, true];
        }
    }
}
