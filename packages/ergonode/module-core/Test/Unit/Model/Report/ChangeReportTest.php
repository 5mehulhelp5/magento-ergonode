<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Report;

use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ChangeReportTest extends TestCase
{
    public function testLargeReportSpillsWithoutLosingEntriesAndResetStartsEmpty(): void
    {
        $report = new ChangeReport(new Json());
        $message = str_repeat('report detail ', 100);
        $before = memory_get_usage();
        for ($index = 0; $index < 10000; ++$index) {
            $report->add('option', (string)$index, 'unchanged', $message);
        }
        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $before);
        self::assertSame([], iterator_to_array($report->iterateEntries()));
        $count = 0;
        foreach ($report->iterateEntries(true) as $entry) {
            self::assertSame((string)$count++, $entry['identifier']);
            self::assertSame($message, $entry['message']);
        }
        self::assertSame(10000, $count);
        $report->reset();
        self::assertSame([], $report->getEntries(true));
    }

    public function testInterleavedReadersAndAppendPreserveTheirPositions(): void
    {
        $report = new ChangeReport(new Json());
        $report->add('attribute', 'first', 'inserted');
        $report->add('attribute', 'second', 'updated');
        $first = $report->iterateEntries();
        self::assertSame('first', $first->current()['identifier']);
        $second = $report->iterateEntries();
        self::assertSame('first', $second->current()['identifier']);
        $report->add('attribute', 'third', 'inserted');
        $first->next();
        $second->next();
        self::assertSame('second', $first->current()['identifier']);
        self::assertSame('second', $second->current()['identifier']);
        $first->next();
        self::assertFalse($first->valid());
        self::assertCount(3, $report->getEntries());
    }
}
