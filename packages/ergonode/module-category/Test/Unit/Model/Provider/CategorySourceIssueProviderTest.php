<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Provider;

use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Provider\CategorySourceIssueProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use PHPUnit\Framework\TestCase;

class CategorySourceIssueProviderTest extends TestCase
{
    public function testListsMissingMappedCategoriesIncludingThoseWithProtectedChildren(): void
    {
        $state = $this->createStub(CategoryTreeSourceState::class);
        $state->method('get')->willReturn([
            'status' => 'available', 'checked_at' => '2026-09-09 12:00:00',
            'snapshot_at' => '2026-09-09 12:00:00', 'requires_refresh' => false,
            'missing_codes' => ['removed', 'gone'],
        ]);
        $mappings = $this->createStub(CategoryMappingQuery::class);
        $mappings->method('getMappingsByTreeId')->willReturn(['present' => 10, 'removed' => 11, 'gone' => 12]);
        $magento = $this->createStub(MagentoCategoryProvider::class);
        $magento->method('getCategories')->willReturn([
            10 => ['label' => 'Present'], 11 => ['label' => 'Preserved parent'],
            13 => ['label' => 'Unmanaged child', 'parent_id' => 11],
        ]);

        $result = (new CategorySourceIssueProvider($state, $mappings, $magento))->get(7, 2);

        self::assertSame([
            ['code' => 'removed', 'magento_category_id' => 11, 'label' => 'Preserved parent'],
        ], $result['missing_categories']);
    }

    public function testUnavailableSourceDoesNotClassifySnapshotDifferencesAsRemovedCategories(): void
    {
        $state = $this->createStub(CategoryTreeSourceState::class);
        $state->method('get')->willReturn([
            'status' => 'unavailable', 'checked_at' => '2026-09-09 12:00:00',
            'snapshot_at' => '2026-09-08 12:00:00', 'requires_refresh' => true,
        ]);
        $mappings = $this->createMock(CategoryMappingQuery::class);
        $mappings->expects(self::never())->method('getMappingsByTreeId');
        $result = (new CategorySourceIssueProvider(
            $state,
            $mappings,
            $this->createStub(MagentoCategoryProvider::class)
        ))->get(7, 2);

        self::assertSame([], $result['missing_categories']);
    }
}
