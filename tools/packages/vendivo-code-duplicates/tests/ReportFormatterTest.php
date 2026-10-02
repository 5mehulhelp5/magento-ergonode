<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vendivo\CodeDuplicates\AnalysisReport;
use Vendivo\CodeDuplicates\DuplicateGroup;
use Vendivo\CodeDuplicates\DuplicateOccurrence;
use Vendivo\CodeDuplicates\ReportFormatter;

#[CoversClass(ReportFormatter::class)]
#[CoversClass(AnalysisReport::class)]
final class ReportFormatterTest extends TestCase
{
    public function testHumanOutputIsLimitedByDefault(): void
    {
        $output = (new ReportFormatter())->format($this->report(), 'text');

        self::assertStringContainsString('Showing 10.', $output);
        self::assertStringContainsString('[10]', $output);
        self::assertStringNotContainsString('[11]', $output);
        self::assertStringContainsString('2 additional group(s) hidden.', $output);
    }

    public function testAgentOutputIsCompactAndActionable(): void
    {
        $output = (new ReportFormatter())->format($this->report(), 'ai');

        self::assertStringContainsString('status=found files=24 groups=12 shown=5 min_score=40', $output);
        self::assertStringContainsString('DUPLICATE id=5', $output);
        self::assertStringNotContainsString('DUPLICATE id=6', $output);
        self::assertStringContainsString('NEXT inspect shown groups;', $output);
    }

    public function testJsonOutputAlwaysContainsEveryGroup(): void
    {
        $output = (new ReportFormatter())->format($this->report(), 'json', 1);

        /** @var array{status: string, duplicateGroups: int, duplicates: list<mixed>} $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('found', $decoded['status']);
        self::assertSame(12, $decoded['duplicateGroups']);
        self::assertCount(12, $decoded['duplicates']);
    }

    public function testAgentErrorsUseOneMachineFriendlyLine(): void
    {
        $output = (new ReportFormatter())->formatError('Missing path', 'ai');

        self::assertSame("CODE_DUPLICATES status=error message=\"Missing path\"\n", $output);
    }

    private function report(): AnalysisReport
    {
        $groups = [];
        for ($index = 1; $index <= 12; $index++) {
            $group = new DuplicateGroup(100 - $index, 3);
            $group->add(new DuplicateOccurrence(
                $index * 2,
                0,
                3,
                "src/Example{$index}.php",
                10,
                20
            ));
            $group->add(new DuplicateOccurrence(
                ($index * 2) + 1,
                0,
                3,
                "src/Copy{$index}.php",
                30,
                40
            ));
            $groups[] = $group;
        }

        return new AnalysisReport(24, 40, $groups);
    }
}
