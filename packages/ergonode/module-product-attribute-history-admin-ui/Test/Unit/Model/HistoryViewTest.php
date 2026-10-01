<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistoryAdminUi\Test\Unit\Model;

use Ergonode\ProductAttributeHistoryAdminUi\Model\HistoryView;
use PHPUnit\Framework\TestCase;

class HistoryViewTest extends TestCase
{
    public function testSummaryDefersUnchangedOptionsButRetainsChangedCounterpartsAndVisibleCounts(): void
    {
        $state = $this->state();
        $summary = (new HistoryView())->summary($state);

        self::assertSame(['red'], array_column($summary['options']['source']['colour'], 'code'));
        self::assertSame(['1'], array_column($summary['options']['target']['color'], 'code'));
        self::assertSame(1, $summary['option_counts']['source']['colour']);
        self::assertSame(2, $summary['option_counts']['target']['color']);
        self::assertSame([], $summary['options']['target']['size']);
        self::assertCount(2, $state['options']['target']['color']);
        self::assertSame($state['changes'], $summary['changes']);
    }

    public function testExpansionReturnsOnlyRequestedHistoricalPairIncludingPreviousMapping(): void
    {
        $state = $this->state();
        $state['changes'][] = ['side' => 'target', 'code' => 'color',
            'before' => $state['target'][0], 'after' => null];
        $state['target'] = [];
        $options = (new HistoryView())->options($state, 'target', 'color');

        self::assertSame($state['options']['target']['color'], $options['target']['color']);
        self::assertSame($state['options']['source']['colour'], $options['source']['colour']);
        self::assertArrayNotHasKey('size', $options['target']);
    }

    public function testSearchFindsUnloadedOptionsAndLinkedLabelsWithoutReturningRows(): void
    {
        $view = new HistoryView();
        $state = $this->state();
        self::assertSame(['color'], $view->search($state, 'target', 'BLUE'));
        self::assertSame(['color'], $view->search($state, 'target', 'Czerwony'));
        self::assertSame([], $view->search($state, 'source', 'Czerwony'));
        self::assertSame(['size'], $view->search($state, 'target', 'Large'));
    }

    public function testMissingOptionHistoryRemainsUnavailableAndEmptyHistoryStaysEmpty(): void
    {
        $view = new HistoryView();
        self::assertNull($view->summary(null));
        self::assertSame(['options' => null, 'changes_only' => false], $view->summary(['options' => null]));
        self::assertNull($view->options(['options' => null], 'target', 'color'));
        self::assertSame([], $view->search(['options' => null], 'target', 'blue'));
        self::assertSame(['source' => [], 'target' => []], $view->summary([
            'options' => ['source' => [], 'target' => []], 'changes' => [],
        ])['options']);
    }

    public function testChangesOnlyOmitsOneThousandUnchangedAttributesAndRetainsOptionParents(): void
    {
        $state = $this->state();
        for ($index = 0; $index < 1000; $index++) {
            $state['target'][] = ['code' => 'unchanged_' . $index, 'mapped_code' => null];
        }
        $summary = (new HistoryView())->summary($state, true);
        self::assertTrue($summary['changes_only']);
        self::assertSame(['colour'], array_column($summary['source'], 'code'));
        self::assertSame(['color'], array_column($summary['target'], 'code'));
        self::assertSame(['1'], array_column($summary['options']['target']['color'], 'code'));
        self::assertArrayNotHasKey('size', $summary['options']['target']);
        self::assertSame(0, $summary['option_counts']['target']['color']);
        self::assertCount(1001, (new HistoryView())->summary($state, false)['target']);
    }

    public function testChangesOnlyPreservesRemovalAndItsFormerCounterpartWithoutOptionHistory(): void
    {
        $state = ['source' => [], 'target' => [['code' => 'color', 'mapped_code' => null]],
            'options' => null, 'changes' => [['side' => 'source', 'code' => 'colour',
                'before' => ['code' => 'colour', 'mapped_code' => 'color'], 'after' => null]]];
        $summary = (new HistoryView())->summary($state, true);
        self::assertSame($state['changes'], $summary['changes']);
        self::assertSame($state['target'], $summary['target']);
        self::assertNull($summary['options']);
        $state['changes'] = [];
        self::assertSame([], (new HistoryView())->summary($state, true)['target']);
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        $red = $this->option('red', 'Czerwony', '1', 'color');
        $redTarget = $this->option('1', 'Red', 'red', 'colour');
        return [
            'source' => [['code' => 'colour', 'mapped_code' => 'color']],
            'target' => [['code' => 'color', 'mapped_code' => 'colour']],
            'options' => [
                'source' => ['colour' => [$red, $this->option('blue', 'Blue')]],
                'target' => ['color' => [$redTarget, $this->option('2', 'Blue')],
                    'size' => [$this->option('1', 'Large')]],
            ],
            'changes' => [['entity' => 'option', 'side' => 'source', 'attribute_code' => 'colour',
                'code' => 'red', 'actions' => ['renamed'], 'before' => $red, 'after' => $red]],
        ];
    }

    /** @return array<string, mixed> */
    private function option(string $code, string $label, ?string $mapped = null, ?string $parent = null): array
    {
        return ['code' => $code, 'label' => $label, 'type' => 'option', 'active' => true,
            'mapped_code' => $mapped, 'mapped_attribute_code' => $parent];
    }
}
