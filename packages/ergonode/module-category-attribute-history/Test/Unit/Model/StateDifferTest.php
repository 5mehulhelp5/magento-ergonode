<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Test\Unit\Model;

use Ergonode\CategoryAttributeHistory\Model\StateDiffer;
use PHPUnit\Framework\TestCase;

class StateDifferTest extends TestCase
{
    public function testDraftTransitionsAreChangesEvenWithoutAConnection(): void
    {
        $unmapped = $this->attribute('description', null) + ['is_draft' => false];
        $draft = array_replace($unmapped, ['is_draft' => true]);
        $complete = array_replace($unmapped, ['mapped_code' => 'source_description']);
        $differ = new StateDiffer();
        foreach (['source', 'target'] as $side) {
            foreach ([
                [$unmapped, $draft, ['draft_added']],
                [$draft, $unmapped, ['draft_removed']],
                [$draft, $complete, ['connected']],
                [$complete, $draft, ['disconnected']],
                [$draft, $draft, []],
            ] as [$before, $after, $actions]) {
                $previous = ['source' => [], 'target' => []];
                $current = $previous;
                $previous[$side] = [$before];
                $current[$side] = [$after];
                $changes = $differ->compare($previous, $current);
                self::assertSame($actions, $changes[0]['actions'] ?? []);
                self::assertCount($actions === [] ? 0 : 1, $changes);
            }
        }
    }

    public function testCapturesUnmappingAndExclusionWithOriginalMetadata(): void
    {
        $attribute = $this->attribute('material', 'fabric');
        $after = array_replace($attribute, ['mapped_code' => null, 'active' => false, 'label' => 'New material']);
        $changes = (new StateDiffer())->compare(
            ['source' => [$attribute], 'target' => []],
            ['source' => [$after], 'target' => []]
        );

        self::assertCount(1, $changes);
        self::assertSame(['renamed', 'excluded', 'disconnected'], $changes[0]['actions']);
        self::assertSame($attribute, $changes[0]['before']);
        self::assertSame($after, $changes[0]['after']);
    }

    public function testDistinguishesRemappingCreationAndRemovalWithoutArrayOrderNoise(): void
    {
        $before = [
            'source' => [$this->attribute('size', 'size'), $this->attribute('old', null)], 'target' => [],
        ];
        $after = [
            'source' => [$this->attribute('new', null), $this->attribute('size', 'clothing_size')], 'target' => [],
        ];
        $changes = (new StateDiffer())->compare($before, $after);

        self::assertSame(['new', 'old', 'size'], array_column($changes, 'code'));
        self::assertSame([['created'], ['deleted'], ['reconnected']], array_column($changes, 'actions'));
        self::assertSame(
            [],
            (new StateDiffer())->compare($after, ['source' => array_reverse($after['source']), 'target' => []])
        );
    }

    /** @return array<string, mixed> */
    private function attribute(string $code, ?string $mappedCode): array
    {
        return [
            'code' => $code, 'label' => ucfirst($code), 'type' => 'text',
            'scope' => 'global', 'active' => true, 'mapped_code' => $mappedCode,
        ];
    }
}
