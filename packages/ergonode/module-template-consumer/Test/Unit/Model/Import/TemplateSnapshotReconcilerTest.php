<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Model\Import\TemplateCacheWriter;
use Ergonode\TemplateConsumer\Model\Import\TemplateSnapshotReconciler;
use PHPUnit\Framework\TestCase;

class TemplateSnapshotReconcilerTest extends TestCase
{
    public function testMarksAndReportsTemplatesMissingFromCompletedList(): void
    {
        $writer = $this->createMock(TemplateCacheWriter::class);
        $writer->expects(self::once())
            ->method('markMissingAsDeleted')
            ->with(['active_template'])
            ->willReturn(['missing_template']);
        $report = $this->createMock(ChangeReport::class);
        $report->expects(self::once())
            ->method('add')
            ->with(
                'template',
                'missing_template',
                ChangeReport::ACTION_UPDATED,
                'Template is absent from the completed Ergonode templateList scan.',
                ['deleted' => true]
            );

        self::assertSame(
            1,
            (new TemplateSnapshotReconciler($writer, $report))->reconcile(['active_template'])
        );
    }
}
