<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use InvalidArgumentException;
use JsonException;

final class ReportFormatter
{
    /** @throws JsonException */
    public function format(AnalysisReport $report, string $format, ?int $limit = null): string
    {
        if ($limit !== null && $limit < 0) {
            throw new InvalidArgumentException('The output limit cannot be negative.');
        }

        return match ($format) {
            'text' => $this->formatForHuman($report, $limit ?? 10),
            'ai' => $this->formatForAgent($report, $limit ?? 5),
            'json' => $this->formatAsJson($report),
            default => throw new InvalidArgumentException("Unsupported output format: {$format}"),
        };
    }

    /** @throws JsonException */
    public function formatError(string $message, string $format): string
    {
        if ($format === 'json') {
            return json_encode(
                ['schemaVersion' => 1, 'status' => 'error', 'message' => $message],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
        }
        if ($format === 'ai') {
            return sprintf(
                "CODE_DUPLICATES status=error message=%s\n",
                json_encode($message, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)
            );
        }

        return "Code duplicate analysis failed: {$message}\n";
    }

    private function formatForHuman(AnalysisReport $report, int $limit): string
    {
        if (!$report->hasDuplicates()) {
            return sprintf(
                "No structural duplicates found in %d PHP files (minimum score: %d).\n",
                $report->fileCount,
                $report->minimumScore
            );
        }

        $visibleGroups = $this->visibleGroups($report->groups, $limit);
        $output = sprintf(
            "Structural duplicates: %d group(s) in %d PHP files (minimum score: %d). Showing %d.\n",
            count($report->groups),
            $report->fileCount,
            $report->minimumScore,
            count($visibleGroups)
        );
        foreach ($visibleGroups as $index => $group) {
            $output .= sprintf(
                "\n[%d] Score %d, %d statement(s), %d occurrence(s)\n",
                $index + 1,
                $group->score,
                $group->statementCount,
                $group->occurrenceCount()
            );
            foreach ($group->occurrences() as $occurrence) {
                $output .= sprintf("  - %s:%d-%d\n", $occurrence->file, $occurrence->startLine, $occurrence->endLine);
            }
        }

        $hidden = count($report->groups) - count($visibleGroups);
        if ($hidden > 0) {
            $output .= sprintf("\n%d additional group(s) hidden. Use --limit=0 to show all.\n", $hidden);
        }

        return $output;
    }

    /** @throws JsonException */
    private function formatForAgent(AnalysisReport $report, int $limit): string
    {
        if (!$report->hasDuplicates()) {
            return sprintf(
                "CODE_DUPLICATES status=ok files=%d groups=0 min_score=%d\n",
                $report->fileCount,
                $report->minimumScore
            );
        }

        $visibleGroups = $this->visibleGroups($report->groups, $limit);
        $output = sprintf(
            "CODE_DUPLICATES status=found files=%d groups=%d shown=%d min_score=%d\n",
            $report->fileCount,
            count($report->groups),
            count($visibleGroups),
            $report->minimumScore
        );
        foreach ($visibleGroups as $index => $group) {
            $output .= sprintf(
                "DUPLICATE id=%d score=%d statements=%d occurrences=%d\n",
                $index + 1,
                $group->score,
                $group->statementCount,
                $group->occurrenceCount()
            );
            foreach ($group->occurrences() as $occurrence) {
                $output .= sprintf("- %s:%d-%d\n", $occurrence->file, $occurrence->startLine, $occurrence->endLine);
            }
        }

        if (count($visibleGroups) < count($report->groups)) {
            $output .= 'NEXT inspect shown groups; use --format=json for the complete report '
                . "or --limit=0 for all concise findings.\n";
        } else {
            $output .= "NEXT inspect the listed duplicate groups.\n";
        }

        return $output;
    }

    /** @throws JsonException */
    private function formatAsJson(AnalysisReport $report): string
    {
        return json_encode(
            $report->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    }

    /**
     * @param list<DuplicateGroup> $groups
     * @return list<DuplicateGroup>
     */
    private function visibleGroups(array $groups, int $limit): array
    {
        return $limit === 0 ? $groups : array_slice($groups, 0, $limit);
    }
}
