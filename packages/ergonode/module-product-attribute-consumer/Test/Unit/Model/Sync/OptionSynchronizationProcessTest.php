<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttributeConsumer\Model\Import\OptionSnapshotReconciler;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\ProductAttributeConsumer\Model\Sync\MagentoOptionSyncer;
use Ergonode\ProductAttributeConsumer\Model\Sync\OptionSynchronizationProcess;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class OptionSynchronizationProcessTest extends TestCase
{
    public function testRefreshesAndSynchronizesOneMappingWithMappingsAlwaysEnabled(): void
    {
        $mapping = [
            'mapping_id' => 14,
            'ergonode_attribute_code' => 'status',
            'magento_attribute_code' => 'status',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
        ];
        $stats = $this->stats(['linked' => 2, 'mappings_inserted' => 2]);
        $provider = $this->createMock(AttributeMappingProvider::class);
        $provider->expects(self::once())->method('getMappingRow')->with(14)->willReturn($mapping);
        $compatibility = $this->createMock(AttributeTypeCompatibilityInterface::class);
        $compatibility->expects(self::once())
            ->method('canMapOptions')
            ->with('select', 'select')
            ->willReturn(true);
        $refresher = $this->createMock(AttributeCacheRefresherInterface::class);
        $refresher->expects(self::once())->method('refreshOptions')->with('status');
        $syncer = $this->createMock(MagentoOptionSyncer::class);
        $syncer->expects(self::once())->method('syncAttributeMapping')->with($mapping)->willReturn($stats);
        $config = $this->createStub(ProductAttributeConfigProvider::class);
        $config->method('shouldDeleteMissingMagentoOptions')->willReturn(false);
        $reconciler = $this->createMock(OptionSnapshotReconciler::class);
        $reconciler->expects(self::never())->method('reconcile');
        $report = $this->createMock(ChangeReport::class);
        $report->expects(self::once())->method('reset');
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())
            ->method('lock')
            ->with('ergonode_option_synchronization', 0)
            ->willReturn(true);
        $lockManager->expects(self::once())
            ->method('unlock')
            ->with('ergonode_option_synchronization');

        $result = (new OptionSynchronizationProcess(
            $provider,
            $compatibility,
            $refresher,
            $syncer,
            $config,
            $reconciler,
            $report,
            $lockManager,
        ))->execute(14);

        self::assertSame($stats, $result['summary']);
        self::assertSame(14, $result['mappings'][0]['mapping_id']);
        self::assertSame($stats, $result['mappings'][0]['stats']);
    }

    public function testRejectsConcurrentEntryBeforeAnySynchronizationWork(): void
    {
        $provider = $this->createMock(AttributeMappingProvider::class);
        $provider->expects(self::never())->method('getMappingRow');
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(false);
        $process = new OptionSynchronizationProcess(
            $provider,
            $this->createStub(AttributeTypeCompatibilityInterface::class),
            $this->createStub(AttributeCacheRefresherInterface::class),
            $this->createStub(MagentoOptionSyncer::class),
            $this->createStub(ProductAttributeConfigProvider::class),
            $this->createStub(OptionSnapshotReconciler::class),
            $this->createStub(ChangeReport::class),
            $lockManager
        );

        $this->expectExceptionMessage('Option synchronization is already running.');

        $process->execute(14);
    }

    public function testSynchronizesOnlyMappingsFromImportedBatchWithoutResettingParentReport(): void
    {
        $colorMapping = [
            'mapping_id' => 14,
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
        ];
        $provider = $this->createMock(AttributeMappingProvider::class);
        $provider->expects(self::once())->method('getOptionAttributeContexts')->willReturn([
            ['mapping_id' => 14, 'left' => ['code' => 'color']],
            ['mapping_id' => 15, 'left' => ['code' => 'size']],
        ]);
        $provider->expects(self::once())->method('getMappingRow')->with(14)->willReturn($colorMapping);
        $compatibility = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $compatibility->method('canMapOptions')->willReturn(true);
        $refresher = $this->createMock(AttributeCacheRefresherInterface::class);
        $refresher->expects(self::once())->method('refreshOptions')->with('color');
        $syncer = $this->createMock(MagentoOptionSyncer::class);
        $syncer->expects(self::once())
            ->method('syncAttributeMapping')
            ->with($colorMapping)
            ->willReturn($this->stats(['linked' => 1]));
        $report = $this->createMock(ChangeReport::class);
        $report->expects(self::never())->method('reset');
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $result = (new OptionSynchronizationProcess(
            $provider,
            $compatibility,
            $refresher,
            $syncer,
            $this->createStub(ProductAttributeConfigProvider::class),
            $this->createStub(OptionSnapshotReconciler::class),
            $report,
            $lockManager,
        ))->executeForAttributeCodes(['color']);

        self::assertSame(1, $result['summary']['linked']);
        self::assertCount(1, $result['mappings']);
        self::assertSame('color', $result['mappings'][0]['ergonode_attribute_code']);
    }

    public function testFailedFullRefreshCannotStartDeletionOrMagentoSynchronization(): void
    {
        $mapping = [
            'mapping_id' => 14,
            'ergonode_attribute_code' => 'color',
            'magento_attribute_code' => 'color',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            'magento_has_custom_source' => false,
        ];
        $provider = $this->createStub(AttributeMappingProvider::class);
        $provider->method('getMappingRow')->willReturn($mapping);
        $compatibility = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $compatibility->method('canMapOptions')->willReturn(true);
        $refresher = $this->createMock(AttributeCacheRefresherInterface::class);
        $refresher->expects(self::once())->method('refreshOptions')
            ->willThrowException(new LocalizedException(__('Remote refresh failed.')));
        $syncer = $this->createMock(MagentoOptionSyncer::class);
        $syncer->expects(self::never())->method('syncAttributeMapping');
        $config = $this->createStub(ProductAttributeConfigProvider::class);
        $config->method('shouldDeleteMissingMagentoOptions')->willReturn(true);
        $reconciler = $this->createMock(OptionSnapshotReconciler::class);
        $reconciler->expects(self::never())->method('reconcile');
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $process = new OptionSynchronizationProcess(
            $provider,
            $compatibility,
            $refresher,
            $syncer,
            $config,
            $reconciler,
            $this->createStub(ChangeReport::class),
            $lockManager
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Remote refresh failed.');

        $process->execute(14);
    }

    /**
     * @param array<string, int> $overrides
     * @return array<string, int>
     */
    private function stats(array $overrides = []): array
    {
        return array_replace([
            'created' => 0,
            'linked' => 0,
            'mappings_inserted' => 0,
            'mappings_updated' => 0,
            'labels_updated' => 0,
            'sort_order_updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => 0,
        ], $overrides);
    }
}
