<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreePayloadBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryTreePayloadBuilderTest extends TestCase
{
    public function testZeroParentSurvivesPayloadAndSnapshotRoundTrip(): void
    {
        $items = [
            ['code' => '0', 'parent_code' => null],
            ['code' => '123', 'parent_code' => '0'],
            ['code' => '001', 'parent_code' => '123'],
        ];
        $ids = ['0' => 'zero', '123' => 'numeric', '001' => 'leading-zero'];
        $builder = new CategoryTreePayloadBuilder();
        $payload = $builder->build($items, $ids);
        self::assertCount(1, $payload);
        self::assertSame('numeric', $payload[0]['children'][0]['category_id']);
        self::assertSame($items, $builder->snapshotLayout($payload, $ids));
    }

    public function testBuildsOrderedNestedTree(): void
    {
        $result = (new CategoryTreePayloadBuilder())->build([
            ['code' => 'child_b', 'parent_code' => 'root', 'sort_order' => 20],
            ['code' => 'root', 'parent_code' => null, 'sort_order' => 0],
            ['code' => 'child_a', 'parent_code' => 'root', 'sort_order' => 10],
        ], ['root' => '1', 'child_a' => '2', 'child_b' => '3']);

        self::assertSame([[
            'category_id' => '1',
            'children' => [
                ['category_id' => '2', 'children' => []],
                ['category_id' => '3', 'children' => []],
            ],
        ]], $result);
    }

    public function testRejectsCycle(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('contains a cycle');

        (new CategoryTreePayloadBuilder())->build([
            ['code' => 'a', 'parent_code' => 'b'],
            ['code' => 'b', 'parent_code' => 'a'],
        ], ['a' => '1', 'b' => '2']);
    }

    public function testPreservesStableSiblingOrderAndBuildsSnapshotFromTheSamePayload(): void
    {
        $builder = new CategoryTreePayloadBuilder();
        $ids = ['root' => '1', 'second-root' => '2', 'b' => '3', 'a' => '4', 'leaf' => '5'];
        $payload = $builder->build([
            ['code' => 'b', 'parent_code' => 'root', 'sort_order' => 0],
            ['code' => 'leaf', 'parent_code' => 'a', 'sort_order' => 0],
            ['code' => 'root', 'parent_code' => null, 'sort_order' => 0],
            ['code' => 'a', 'parent_code' => 'root', 'sort_order' => 0],
            ['code' => 'second-root', 'parent_code' => null, 'sort_order' => 0],
        ], $ids);

        self::assertSame([
            ['code' => 'root', 'parent_code' => null],
            ['code' => 'b', 'parent_code' => 'root'],
            ['code' => 'a', 'parent_code' => 'root'],
            ['code' => 'leaf', 'parent_code' => 'a'],
            ['code' => 'second-root', 'parent_code' => null],
        ], $builder->snapshotLayout($payload, $ids));
        self::assertSame(['1', '2'], array_column($payload, 'category_id'));
        self::assertSame(['3', '4'], array_column($payload[0]['children'], 'category_id'));
    }
    public function testCheckedBranchDoesNotHideDisconnectedCycle(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('contains a cycle');
        (new CategoryTreePayloadBuilder())->build([
            ['code' => 'root', 'parent_code' => null],
            ['code' => 'leaf', 'parent_code' => 'root'],
            ['code' => 'a', 'parent_code' => 'b'],
            ['code' => 'b', 'parent_code' => 'a'],
        ], ['root' => '1', 'leaf' => '2', 'a' => '3', 'b' => '4']);
    }
}
