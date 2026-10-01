<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Config\TemplateAttributeConfigProvider;
use Ergonode\TemplateAttributeConsumer\Model\Import\ImportedTemplateSynchronizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateAttributeSetSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateStructureSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleaner;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;
use PHPUnit\Framework\TestCase;

class ImportedTemplateSynchronizerTest extends TestCase
{
    public function testDeletedTemplateCleanupHonorsSynchronizationSetting(): void
    {
        $config = $this->createStub(TemplateAttributeConfigProvider::class);
        $config->method('shouldSyncAttributes')->willReturnOnConsecutiveCalls(false, true);
        $cleaner = $this->createMock(TemplateStructureCleaner::class);
        $cleaner->expects(self::once())->method('removeTemplate')->with('deleted');
        $synchronizer = new ImportedTemplateSynchronizer(
            $config,
            $this->createStub(TemplateCacheProvider::class),
            $this->createStub(MagentoTemplateStructureSyncer::class),
            $this->createStub(MagentoTemplateAttributeSetSyncer::class),
            $this->createStub(ChangeReport::class),
            $cleaner
        );
        $synchronizer->execute([], ['deleted']);
        $synchronizer->execute([], ['deleted']);
    }

    public function testDoesNothingWhenAttributeSynchronizationIsDisabled(): void
    {
        $config = $this->createStub(TemplateAttributeConfigProvider::class);
        $config->method('shouldSyncAttributes')->willReturn(false);
        $cache = $this->createMock(TemplateCacheProvider::class);
        $cache->expects(self::never())->method('getTemplateStructure');

        $this->synchronizer($config, $cache)->execute(['product']);
    }

    public function testSynchronizesMappedTemplateStructureAndSkipsUnmappedTemplate(): void
    {
        $config = $this->createStub(TemplateAttributeConfigProvider::class);
        $config->method('shouldSyncAttributes')->willReturn(true);
        $config->method('shouldSyncSections')->willReturn(true);
        $cache = $this->createMock(TemplateCacheProvider::class);
        $cache->expects(self::exactly(2))->method('getTemplateStructure')->willReturnMap([
            ['mapped', ['code' => 'mapped', 'attribute_set_id' => 12, 'sections' => []]],
            ['unmapped', ['code' => 'unmapped', 'attribute_set_id' => null, 'sections' => []]],
        ]);
        $structureSyncer = $this->createMock(MagentoTemplateStructureSyncer::class);
        $structureSyncer->expects(self::once())->method('syncTemplate')->with('mapped', null, false, true);
        $attributeSyncer = $this->createMock(MagentoTemplateAttributeSetSyncer::class);
        $attributeSyncer->expects(self::never())->method('syncTemplate');
        $report = $this->createMock(ChangeReport::class);
        $report->expects(self::once())->method('add')->with(
            'template_structure',
            'unmapped',
            ChangeReport::ACTION_SKIPPED,
            'Skipped template structure sync because Magento attribute set is not assigned.',
            []
        );

        (new ImportedTemplateSynchronizer(
            $config,
            $cache,
            $structureSyncer,
            $attributeSyncer,
            $report,
            $this->createStub(TemplateStructureCleaner::class)
        ))
            ->execute(['mapped', 'unmapped']);
    }

    public function testSynchronizesAttributesIntoCommonGroupWhenSectionsAreDisabled(): void
    {
        $config = $this->createStub(TemplateAttributeConfigProvider::class);
        $config->method('shouldSyncAttributes')->willReturn(true);
        $config->method('shouldSyncSections')->willReturn(false);
        $cache = $this->createStub(TemplateCacheProvider::class);
        $cache->method('getTemplateStructure')->willReturn([
            'code' => 'product',
            'attribute_set_id' => 12,
            'sections' => [],
        ]);
        $structureSyncer = $this->createMock(MagentoTemplateStructureSyncer::class);
        $structureSyncer->expects(self::never())->method('syncTemplate');
        $attributeSyncer = $this->createMock(MagentoTemplateAttributeSetSyncer::class);
        $attributeSyncer->expects(self::once())->method('syncTemplate')->with('product', false, true);

        (new ImportedTemplateSynchronizer(
            $config,
            $cache,
            $structureSyncer,
            $attributeSyncer,
            $this->createStub(ChangeReport::class),
            $this->createStub(TemplateStructureCleaner::class)
        ))->execute(['product']);
    }

    private function synchronizer(
        TemplateAttributeConfigProvider $config,
        TemplateCacheProvider $cache
    ): ImportedTemplateSynchronizer {
        return new ImportedTemplateSynchronizer(
            $config,
            $cache,
            $this->createStub(MagentoTemplateStructureSyncer::class),
            $this->createStub(MagentoTemplateAttributeSetSyncer::class),
            $this->createStub(ChangeReport::class),
            $this->createStub(TemplateStructureCleaner::class)
        );
    }
}
