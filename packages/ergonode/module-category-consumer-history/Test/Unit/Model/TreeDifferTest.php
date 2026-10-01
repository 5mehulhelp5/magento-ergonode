<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Unit\Model;

use Ergonode\CategoryConsumerHistory\Model\TreeDiffer;
use PHPUnit\Framework\TestCase;

class TreeDifferTest extends TestCase
{
    public function testReturnsEveryNetChangeInOneDifference(): void
    {
        $before = [
            'source' => [
                $this->source('chairs', 'Chairs', 'living-room', 1, 11, true),
                $this->source('deleted', 'Deleted', 'living-room', 2, null, true),
            ],
            'target' => [$this->target('11', 'Old Chairs', '2', 1, 'chairs')],
        ];
        $after = [
            'source' => [
                $this->source('chairs', 'Dining Chairs', 'dining-room', 4, 12, false),
                $this->source('created', 'Created', 'dining-room', 2, 15, true),
            ],
            'target' => [$this->target('11', 'Old Chairs', '3', 2, 'chairs')],
        ];

        $result = (new TreeDiffer())->diff($before, $after);
        $changes = [];
        foreach ($result['changes'] as $change) {
            $changes[$change['entity_type'] . ':' . $change['entity_identifier']] = $change;
        }

        self::assertSame(
            ['moved', 'reordered', 'renamed', 'excluded', 'reconnected'],
            $changes['source:chairs']['actions']
        );
        self::assertSame(['created', 'connected'], $changes['source:created']['actions']);
        self::assertSame(['deleted'], $changes['source:deleted']['actions']);
        self::assertSame(['moved', 'reordered'], $changes['target:11']['actions']);
        self::assertSame(4, $result['summary']['changes']);
        self::assertSame(3, $result['summary']['categories']);
        self::assertSame(2, $result['summary']['moved']);
    }

    public function testDistinguishesSourceMovementHiddenByManualLayout(): void
    {
        $before = ['source' => [[
            'identifier' => 'chairs',
            'label' => 'Chairs',
            'parent_identifier' => 'manual-parent',
            'source_parent_identifier' => 'old-source-parent',
            'sort_order' => 4,
            'source_sort_order' => 1,
            'magento_category_id' => null,
            'magento_label' => null,
            'active' => true,
        ]], 'target' => []];
        $after = $before;
        $after['source'][0]['source_parent_identifier'] = 'new-source-parent';
        $after['source'][0]['source_sort_order'] = 2;

        $result = (new TreeDiffer())->diff($before, $after);

        self::assertSame(['source_moved', 'source_reordered'], $result['changes'][0]['actions']);
    }

    /** @return array<string, int|string|bool|null> */
    private function source(
        string $identifier,
        string $label,
        ?string $parent,
        int $position,
        ?int $magentoCategoryId,
        bool $active
    ): array {
        return [
            'identifier' => $identifier,
            'label' => $label,
            'parent_identifier' => $parent,
            'source_parent_identifier' => $parent,
            'sort_order' => $position,
            'source_sort_order' => $position,
            'magento_category_id' => $magentoCategoryId,
            'magento_label' => $magentoCategoryId !== null ? '#' . $magentoCategoryId : null,
            'active' => $active,
        ];
    }

    /** @return array<string, int|string|bool|null> */
    private function target(
        string $identifier,
        string $label,
        ?string $parent,
        int $position,
        ?string $categoryCode
    ): array {
        return [
            'identifier' => $identifier,
            'label' => $label,
            'parent_identifier' => $parent,
            'sort_order' => $position,
            'level' => 2,
            'path' => '1/2/' . $identifier,
            'active' => true,
            'category_code' => $categoryCode,
        ];
    }
}
