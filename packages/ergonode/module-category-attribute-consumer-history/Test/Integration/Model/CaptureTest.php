<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerHistory\Test\Integration\Model;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Api\AttributeSnapshotRemoverInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingUpdaterInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeRegistryRefresherInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeSnapshotRemoverInterface;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryMappedAttributeBackfiller;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\MappingSynchronization;
use Ergonode\CategoryAttributeHistory\Api\HistoryQueryInterface;
use Ergonode\CategoryAttributeHistory\Api\SourceSnapshotProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeMappingSaver;
use RuntimeException;

#[AppIsolation(true), DbIsolation(true)]
class CaptureTest extends TestCase
{
    public function testCategoryRemovalCapturesFreshMetadataAndPreservesSavedState(): void
    {
        $this->seed('Original');
        $objects = Bootstrap::getObjectManager();
        $source = $objects->get(SourceSnapshotProviderInterface::class);
        self::assertSame('Original', $source->getAttributeMap()['history_category']['label']);
        $this->seed('Renamed');
        self::assertSame('Renamed', $source->getAttributeMap()['history_category']['label']);
        $query = $objects->get(HistoryQueryInterface::class);
        $total = $query->getOperations()['total'];
        $objects->get(CategoryAttributeSnapshotRemoverInterface::class)->remove('history_category');
        $page = $query->getOperations(1);
        self::assertSame($total + 1, $page['total']);
        self::assertSame('delete_snapshot', $page['items'][0]['operation_code']);
        $state = $query->getState($page['items'][0]['operation_id']);
        self::assertSame('Renamed', $state['changes'][0]['before']['label']);
        self::assertContains('deleted', $state['changes'][0]['actions']);
        $this->seed('Later');
        self::assertSame($state, $query->getState($page['items'][0]['operation_id']));
    }

    #[DataProvider('saveEntrypoints')]
    public function testOuterSaveRecordsBackfillFailureOnceIncludingThePersistedMapping(bool $sharedBoundary): void
    {
        $this->seed('Category title');
        $objects = Bootstrap::getObjectManager();
        $failure = new RuntimeException('backfill failed');
        $backfiller = $this->createMock(CategoryMappedAttributeBackfiller::class);
        $backfiller->expects(self::once())->method('execute')->willThrowException($failure);
        $updater = $objects->create(CategoryAttributeMappingUpdaterInterface::class, [
            'synchronization' => $objects->create(MappingSynchronization::class, ['backfiller' => $backfiller]),
        ]);
        $query = $objects->get(HistoryQueryInterface::class);
        $total = $query->getOperations()['total'];
        try {
            $mappings = [[
                'left' => ['code' => 'history_category'], 'right' => ['code' => 'meta_title'],
            ]];
            if ($sharedBoundary) {
                $objects->create(MappingSynchronization::class, ['backfiller' => $backfiller])->execute(
                    fn (): array => $objects->get(CategoryAttributeMappingSaver::class)->save($mappings, [])
                );
            } else {
                $updater->save($mappings, []);
            }
            self::fail('Expected the original backfill exception.');
        } catch (RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
        $page = $query->getOperations(1);
        self::assertSame($total + 1, $page['total']);
        self::assertSame('save', $page['items'][0]['operation_code']);
        self::assertSame('failed', $page['items'][0]['status']);
        $state = $query->getState($page['items'][0]['operation_id']);
        $source = array_column($state['source'], null, 'code');
        self::assertSame('meta_title', $source['history_category']['mapped_code']);
    }

    public function testRegistryRefreshAndSharedRemovalAreCapturedThroughMagentoDi(): void
    {
        $this->seed('Original');
        $objects = Bootstrap::getObjectManager();
        $definitions = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $definitions->expects(self::once())->method('synchronize')->willReturnCallback(function (): void {
            $this->seed('Refreshed');
        });
        $codes = $this->createStub(CategoryAttributeCodeLoaderInterface::class);
        $codes->method('load')->willReturn(['history_category']);
        $refresher = $objects->create(CategoryAttributeRegistryRefresherInterface::class, [
            'definitionSynchronization' => $definitions, 'categoryAttributeCodeLoader' => $codes,
        ]);
        $query = $objects->get(HistoryQueryInterface::class);
        $total = $query->getOperations()['total'];
        self::assertSame(['imported' => 1], $refresher->refresh());
        $page = $query->getOperations(1);
        self::assertSame($total + 1, $page['total']);
        self::assertSame('refresh_snapshot', $page['items'][0]['operation_code']);
        $state = $query->getState($page['items'][0]['operation_id']);
        self::assertSame('Refreshed', $state['changes'][0]['after']['label']);
        $objects->get(AttributeSnapshotRemoverInterface::class)->remove('history_category');
        $page = $query->getOperations(1);
        self::assertSame($total + 2, $page['total']);
        self::assertSame('delete_snapshot', $page['items'][0]['operation_code']);
    }

    public function testSharedSaveGroupsNestedRefreshAndPreservesResult(): void
    {
        $this->seed('Before');
        $objects = Bootstrap::getObjectManager();
        $definitions = $this->createStub(AttributeDefinitionSynchronizationInterface::class);
        $definitions->method('synchronize')->willReturnCallback(fn () => $this->seed('After'));
        $codes = $this->createStub(CategoryAttributeCodeLoaderInterface::class);
        $codes->method('load')->willReturn(['history_category']);
        $refresher = $objects->create(CategoryAttributeRegistryRefresherInterface::class, [
            'definitionSynchronization' => $definitions, 'categoryAttributeCodeLoader' => $codes,
        ]);
        $backfiller = $this->createStub(CategoryMappedAttributeBackfiller::class);
        $backfiller->method('execute')->willReturnCallback(static function () use ($refresher): array {
            $refresher->refresh();
            return ['categories' => 1, 'values' => 2, 'errors' => 0];
        });
        $query = $objects->get(HistoryQueryInterface::class);
        $total = $query->getOperations()['total'];
        $result = $objects->create(MappingSynchronization::class, ['backfiller' => $backfiller])->execute(
            fn (): array => $objects->get(CategoryAttributeMappingSaver::class)->save([[
                'left' => ['code' => 'history_category'], 'right' => ['code' => 'meta_title'],
            ]], [])
        );
        self::assertSame(['categories' => 1, 'values' => 2, 'errors' => 0], $result['value_sync']);
        $page = $query->getOperations(1);
        self::assertSame($total + 1, $page['total']);
        self::assertSame('save', $page['items'][0]['operation_code']);
        self::assertSame('success', $page['items'][0]['status']);
        $state = $query->getState($page['items'][0]['operation_id']);
        $source = array_column($state['source'], null, 'code');
        self::assertSame('After', $source['history_category']['label']);
        self::assertSame('meta_title', $source['history_category']['mapped_code']);
    }

    /** @return array<string, array{bool}> */
    public static function saveEntrypoints(): array
    {
        return ['consumer updater' => [false], 'shared UI boundary' => [true]];
    }

    private function seed(string $label): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insertOnDuplicate($resource->getTableName('ergonode_attribute'), [
            'code' => 'history_category', 'type' => 'text', 'scope' => 'global',
            'labels_json' => json_encode(['en_US' => $label]), 'parameters_json' => '{}',
            'content_hash' => hash('sha256', $label),
        ], ['labels_json', 'content_hash']);
        $connection->insertOnDuplicate($resource->getTableName('ergonode_category_attribute'), [
            'attribute_code' => 'history_category',
        ], ['attribute_code']);
    }
}
