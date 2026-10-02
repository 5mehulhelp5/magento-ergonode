<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Reporting;

use Vendivo\ModuleComposer\ValidationReport;

final class TextReporter
{
    public function render(ValidationReport $report): string
    {
        $lines = [];
        foreach ($report->diagnostics() as $diagnostic) {
            $lines[] = $diagnostic->render();
        }

        $summary = sprintf(
            '[MODULE-COMPOSER] checked=%d errors=%d warnings=%d',
            $report->checkedModules(),
            $report->errorCount(),
            $report->warningCount()
        );
        $lines[] = ($report->hasErrors() ? 'SUMMARY ' : 'OK ') . $summary;

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
