<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistoryAdminUi\Test\Unit\Model;

use Ergonode\CategoryAttributeHistoryAdminUi\Model\HistoryView;
use PHPUnit\Framework\TestCase;

class HistoryViewTest extends TestCase
{
    public function testChangesOnlyRetainsFormerMappingAndOmitsUnchangedAttributes(): void
    {
        $state = ['source' => [], 'target' => [['code' => 'color', 'mapped_code' => null]],
            'changes' => [['side' => 'source', 'code' => 'colour',
                'before' => ['code' => 'colour', 'mapped_code' => 'color'], 'after' => null]]];
        for ($index = 0; $index < 1000; $index++) {
            $state['target'][] = ['code' => 'unchanged_' . $index, 'mapped_code' => null];
        }
        $view = new HistoryView();
        $summary = $view->summary($state);
        self::assertTrue($summary['changes_only']);
        self::assertSame(['color'], array_column($summary['target'], 'code'));
        self::assertSame($state['changes'], $summary['changes']);
        self::assertSame($state['target'], $view->summary($state, false)['target']);
        self::assertTrue($summary['lacks_draft_status']);
    }

    public function testNoChangesStillReportsLegacyDraftStatusAndSupportsEmptyHistory(): void
    {
        $view = new HistoryView();
        $state = ['source' => [['code' => 'legacy']], 'target' => [], 'changes' => []];
        $summary = $view->summary($state);
        self::assertSame([], $summary['source']);
        self::assertTrue($summary['lacks_draft_status']);
        self::assertNull($view->summary(null));
    }

    public function testChangedTargetRetainsItsSourceLabel(): void
    {
        $source = ['code' => 'colour', 'mapped_code' => 'color', 'is_draft' => false];
        $target = ['code' => 'color', 'mapped_code' => 'colour', 'is_draft' => false];
        $state = ['source' => [$source], 'target' => [$target], 'changes' => [
            ['side' => 'target', 'code' => 'color', 'before' => $target, 'after' => $target],
        ]];
        $summary = (new HistoryView())->summary($state);
        self::assertSame([$source], $summary['source']);
        self::assertFalse($summary['lacks_draft_status']);
    }
}
