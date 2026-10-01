<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Support;

use Ergonode\ProductPublisher\Test\E2e\Support\CohortPlanner;
use PHPUnit\Framework\TestCase;

class CohortPlannerTest extends TestCase
{
    public function testSeventyPercentAxesCoverEveryFailureCombination(): void
    {
        $plan = (new CohortPlanner())->plan(
            array_map(static fn (int $id): string => 'entity-' . $id, range(1, 100)),
            'stable-seed'
        );

        self::assertCount(49, array_filter($plan, static fn (string $cohort): bool =>
            $cohort === CohortPlanner::BOTH));
        self::assertCount(21, array_filter($plan, static fn (string $cohort): bool =>
            $cohort === CohortPlanner::PUBLISHED_ONLY));
        self::assertCount(21, array_filter($plan, static fn (string $cohort): bool =>
            $cohort === CohortPlanner::MAPPED_ONLY));
        self::assertCount(9, array_filter($plan, static fn (string $cohort): bool =>
            $cohort === CohortPlanner::NEITHER));
    }

    public function testPlanIsStableRegardlessOfSourceOrdering(): void
    {
        $planner = new CohortPlanner();
        $identifiers = ['one', 'two', 'three', 'four', 'five', 'six', 'seven'];

        self::assertSame(
            $planner->plan($identifiers, 'seed'),
            $planner->plan(array_reverse($identifiers), 'seed')
        );
    }

    public function testThreeRootsExerciseMappedUnmappedAndStaleStates(): void
    {
        $plan = (new CohortPlanner())->planRoots(['41', '51', '61', '2'], 'roots');

        self::assertCount(3, $plan);
        self::assertEqualsCanonicalizing([
            CohortPlanner::BOTH,
            CohortPlanner::PUBLISHED_ONLY,
            CohortPlanner::MAPPED_ONLY,
        ], array_values($plan));
    }

    public function testProductSelectionKeepsEveryAvailableProductType(): void
    {
        $selection = (new CohortPlanner())->selectByGroup([
            'simple' => ['S1', 'S2', 'S3'],
            'configurable' => ['C1', 'C2'],
            'grouped' => ['G1'],
        ], 'products');

        self::assertTrue($selection['G1']);
        self::assertCount(4, array_filter($selection));
        self::assertContains(true, array_intersect_key($selection, array_flip(['S1', 'S2', 'S3'])));
        self::assertContains(true, array_intersect_key($selection, array_flip(['C1', 'C2'])));
    }
}
