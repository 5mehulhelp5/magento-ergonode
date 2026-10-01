<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Api\TemplateStructureLoaderInterface;
use Ergonode\TemplateAttributeConsumer\Model\Import\ImportedTemplateProcessor;
use Ergonode\TemplateAttributeConsumer\Model\Import\TemplateStructureCacheWriter;
use Ergonode\TemplateAttributeConsumer\Model\Import\TemplateStructureNormalizer;
use PHPUnit\Framework\TestCase;

class ImportedTemplateProcessorTest extends TestCase
{
    public function testProcessingOnlyCachesTemplate(): void
    {
        $structureLoader = $this->createMock(TemplateStructureLoaderInterface::class);
        $structureLoader->expects(self::once())
            ->method('load')
            ->with('template_a')
            ->willReturn(['code' => 'template_a']);
        $normalizer = $this->createMock(TemplateStructureNormalizer::class);
        $normalizer->expects(self::once())
            ->method('normalize')
            ->with(['code' => 'template_a'])
            ->willReturn($this->normalizedTemplate());
        $cacheWriter = $this->createMock(TemplateStructureCacheWriter::class);
        $cacheWriter->expects(self::once())
            ->method('save')
            ->with([$this->normalizedTemplate()])
            ->willReturn(['template_a' => ChangeReport::ACTION_INSERTED]);
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects(self::once())
            ->method('add')
            ->with(
                'template_structure',
                'template_a',
                ChangeReport::ACTION_INSERTED,
                'Inserted Ergonode template structure cache.',
                ['sections' => 0]
            );

        $processor = new ImportedTemplateProcessor(
            $structureLoader,
            $normalizer,
            $cacheWriter,
            $changeReport
        );

        $processor->execute(['template_a']);
    }

    /**
     * @return array{
     *     code: string,
     *     sections: array<int, mixed>,
     *     raw: array<string, string>,
     *     hash: string
     * }
     */
    private function normalizedTemplate(): array
    {
        return [
            'code' => 'template_a',
            'sections' => [],
            'raw' => ['code' => 'template_a'],
            'hash' => hash('sha256', 'template_a'),
        ];
    }
}
