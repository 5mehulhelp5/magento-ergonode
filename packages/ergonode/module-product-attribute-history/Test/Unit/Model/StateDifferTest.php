<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Model;

use Ergonode\ProductAttributeHistory\Model\StateDiffer;
use PHPUnit\Framework\TestCase;

class StateDifferTest extends TestCase
{
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

    public function testOptionIdentityIncludesParentAndDetectsRemappingToSameCodeInAnotherAttribute(): void
    {
        $option = $this->attribute('option_1', 'yes') + ['mapped_attribute_code' => 'first'];
        $other = array_replace($option, ['label' => 'Other label']);
        $before = ['source' => [], 'target' => [], 'options' => [
            'source' => [], 'target' => ['first' => [$option], 'second' => [$other]],
        ]];
        $after = $before;
        $after['options']['target']['first'][0]['mapped_attribute_code'] = 'second';
        $after['options']['target']['second'][0]['active'] = false;
        $changes = (new StateDiffer())->compare($before, $after);

        self::assertCount(2, $changes);
        self::assertSame(['first', 'second'], array_column($changes, 'attribute_code'));
        self::assertSame([['reconnected'], ['excluded']], array_column($changes, 'actions'));
        self::assertSame(['option', 'option'], array_column($changes, 'entity'));
        self::assertSame($option, $changes[0]['before']);
        self::assertSame($other, $changes[1]['before']);
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
