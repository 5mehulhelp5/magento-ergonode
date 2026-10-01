<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Api\TemplateImportContributorInterface;
use Ergonode\TemplateConsumer\Api\TemplateLoaderInterface;
use Ergonode\TemplateConsumer\Model\Import\ImportedTemplateProcessor;
use Ergonode\TemplateConsumer\Model\Import\TemplateCacheWriter;
use Ergonode\TemplateConsumer\Model\Import\TemplateNormalizer;
use PHPUnit\Framework\TestCase;

class ImportedTemplateProcessorTest extends TestCase
{
    public function testSnapshotRefreshSkipsMagentoAttributeSetSynchronization(): void
    {
        $loader = $this->createMock(TemplateLoaderInterface::class);
        $loader->expects(self::once())->method('load')->with('template_a')->willReturn(['code' => 'template_a']);
        $normalizer = $this->createStub(TemplateNormalizer::class);
        $normalizer->method('normalize')->willReturn(['code' => 'template_a', 'name' => 'Template A']);
        $writer = $this->createStub(TemplateCacheWriter::class);
        $writer->method('save')->willReturn(['template_a' => ChangeReport::ACTION_INSERTED]);
        $contributor = $this->createMock(TemplateImportContributorInterface::class);
        $contributor->expects(self::once())->method('execute')->with(['template_a']);
        $report = $this->createStub(ChangeReport::class);

        $result = (new ImportedTemplateProcessor(
            $loader,
            $normalizer,
            $writer,
            $report,
            [$contributor]
        ))->process(['template_a']);

        self::assertSame(['imported' => 1, 'changed' => 1, 'unchanged' => 0], $result);
    }
}
