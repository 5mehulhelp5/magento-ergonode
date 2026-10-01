<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Test\Unit\Model;

use Ergonode\CategoryConsumerHistoryAdminUi\Model\HistoryView;
use PHPUnit\Framework\TestCase;

class HistoryViewTest extends TestCase
{
    public function testRetainsCurrentAndPreviousParentsAndBothMappingTargets(): void
    {
        $oldParent = $this->source('old', null, 20);
        $newParent = $this->source('new', null, 21);
        $before = $this->source('moved', 'old', 11);
        $after = $this->source('moved', 'new', 12);
        $state = ['source' => [$oldParent, $newParent, $after, $this->source('unrelated', null, 30)],
            'target' => [$this->target(2, null), $this->target(20, 2), $this->target(21, 2),
                $this->target(11, 20), $this->target(12, 21), $this->target(30, 2)],
            'changes' => [['entity_type' => 'source', 'entity_identifier' => 'moved',
                'before' => $before, 'after' => $after]]];
        $view = new HistoryView();
        $summary = $view->summary($state);
        self::assertSame(['old', 'new', 'moved'], array_column($summary['source'], 'identifier'));
        self::assertSame(['2', '20', '21', '11', '12'], array_column($summary['target'], 'identifier'));
        self::assertSame($state['changes'], $summary['changes']);
        self::assertSame($state['source'], $view->summary($state, false)['source']);
        self::assertTrue($summary['changes_only']);
    }

    public function testDeletedParentAndDistinctSourceParentRemainAvailable(): void
    {
        $deleted = $this->source('deleted', 'grandparent', null);
        $child = $this->source('child', 'deleted', null);
        $child['source_parent_identifier'] = 'source-parent';
        $state = ['source' => [$this->source('grandparent', null, null),
            $this->source('source-parent', null, null), $child], 'target' => [], 'changes' => [
                ['entity_type' => 'source', 'entity_identifier' => 'deleted', 'before' => $deleted, 'after' => null],
                ['entity_type' => 'source', 'entity_identifier' => 'child', 'before' => $child, 'after' => $child],
            ]];
        self::assertSame($state['source'], (new HistoryView())->summary($state)['source']);
    }

    public function testNoChangesDoesNotTransferRowsAndNullStateStaysNull(): void
    {
        $view = new HistoryView();
        $state = ['source' => [$this->source('unchanged', null, null)], 'target' => [], 'changes' => []];
        self::assertSame([], $view->summary($state)['source']);
        self::assertNull($view->summary(null));
    }

    /** @return array<string, mixed> */
    private function source(string $code, ?string $parent, ?int $mapped): array
    {
        return ['identifier' => $code, 'parent_identifier' => $parent,
            'source_parent_identifier' => $parent, 'magento_category_id' => $mapped];
    }

    /** @return array<string, mixed> */
    private function target(int $identifier, ?int $parent): array
    {
        return ['identifier' => (string)$identifier, 'parent_identifier' => $parent, 'category_code' => null];
    }
}
