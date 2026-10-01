<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Reconciliation;

use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use PHPUnit\Framework\TestCase;

class CategoryIdentityResolverTest extends TestCase
{
    public function testDatabaseMappingWinsAndRejectsDraftForMappedCodeOrOccupiedTarget(): void
    {
        $result = $this->resolve(
            $this->sources(['one', 'two']),
            $this->magento([10 => [2, 'Database target'], 11 => [2, 'Different target']]),
            ['one' => 10],
            [
                ['ergonode_code' => 'one', 'magento_category_id' => 11],
                ['ergonode_code' => 'two', 'magento_category_id' => 10],
            ]
        );

        self::assertSame(10, $result['assignments']['one']['magento_category_id']);
        self::assertSame('database', $result['assignments']['one']['source']);
        self::assertNull($result['assignments']['two']['magento_category_id']);
        self::assertCount(2, $result['conflicts']);
    }

    public function testMatchesUniqueNormalizedNameOnlyBelowExpectedParent(): void
    {
        $sources = [
            ['code' => 'parent', 'parent_code' => null, 'label' => 'Parent', 'sort_order' => 0, 'active' => true],
            [
                'code' => 'child',
                'parent_code' => 'parent',
                'label' => '  Krzesła   biurowe ',
                'sort_order' => 1,
                'active' => true,
            ],
        ];
        $result = $this->resolve(
            $sources,
            $this->magento([
                10 => [2, 'Parent'],
                11 => [10, 'krzesła biurowe'],
                12 => [2, 'Krzesła biurowe'],
            ])
        );

        self::assertSame(10, $result['assignments']['parent']['magento_category_id']);
        self::assertSame(11, $result['assignments']['child']['magento_category_id']);
        self::assertSame('name', $result['assignments']['child']['source']);
    }

    public function testLeavesBothSidesUnmatchedWhenSiblingNameIsAmbiguous(): void
    {
        $sources = $this->sources(['one', 'two'], 'Chairs');
        $result = $this->resolve(
            $sources,
            $this->magento([10 => [2, 'Chairs'], 11 => [2, 'Chairs']])
        );

        self::assertNull($result['assignments']['one']['magento_category_id']);
        self::assertNull($result['assignments']['two']['magento_category_id']);
        self::assertNotEmpty($result['conflicts']);
        self::assertFalse($result['deletion_allowed']);
    }

    public function testDatabaseMappedCategoryMovedAcrossParentsIsConsumedGlobally(): void
    {
        $sources = [
            ['code' => 'a', 'parent_code' => null, 'label' => 'A', 'sort_order' => 0, 'active' => true],
            ['code' => 'b', 'parent_code' => null, 'label' => 'B', 'sort_order' => 1, 'active' => true],
            ['code' => 'child', 'parent_code' => 'b', 'label' => 'Child', 'sort_order' => 2, 'active' => true],
        ];
        $result = $this->resolve(
            $sources,
            $this->magento([10 => [2, 'A'], 11 => [2, 'B'], 12 => [10, 'Child']]),
            ['a' => 10, 'b' => 11, 'child' => 12]
        );

        self::assertContains(12, $result['consumed_magento_ids']);
        self::assertNotContains(12, $result['delete_candidates']);
        self::assertSame(11, $result['assignments']['child']['expected_parent_id']);
    }

    public function testExcludedSourceAndMagentoSubtreesAreProtectedAndNeverMatched(): void
    {
        $sources = [
            ['code' => 'blocked', 'parent_code' => null, 'label' => 'Blocked', 'sort_order' => 0, 'active' => false],
            [
                'code' => 'free',
                'parent_code' => null,
                'label' => 'Protected target',
                'sort_order' => 1,
                'active' => true,
            ],
        ];
        $magento = $this->magento([
            10 => [2, 'Blocked'],
            11 => [10, 'Descendant'],
            12 => [2, 'Protected target', false],
            13 => [12, 'Protected descendant'],
        ]);
        $result = $this->resolve($sources, $magento, ['blocked' => 10]);

        self::assertSame('excluded', $result['assignments']['blocked']['source']);
        self::assertNull($result['assignments']['free']['magento_category_id']);
        self::assertEqualsCanonicalizing([10, 11, 12, 13], $result['protected_magento_ids']);
        self::assertSame([], $result['delete_candidates']);
    }

    public function testDeletesOnlyMissingHistoricallyMappedCategories(): void
    {
        $result = $this->resolve(
            $this->sources(['chairs']),
            $this->magento([
                10 => [2, 'Chairs'],
                11 => [2, 'Deleted in Ergonode'],
                12 => [2, 'Magento only'],
            ]),
            ['chairs' => 10, 'deleted-code' => 11]
        );

        self::assertSame([11], $result['delete_candidates']);
        self::assertNotContains(12, $result['delete_candidates']);
    }

    public function testBlocksMappedAncestorDeletionWhenItContainsMagentoOnlyDescendant(): void
    {
        $result = $this->resolve(
            [],
            $this->magento([
                11 => [2, 'Deleted in Ergonode'],
                12 => [11, 'Magento only child'],
            ]),
            ['deleted-code' => 11]
        );

        self::assertSame([], $result['delete_candidates']);
        self::assertFalse($result['deletion_allowed']);
        self::assertSame([], $result['conflicts']);
    }

    public function testReservesDatabaseAndDraftTargetsAcrossBranchesBeforeMatchingNames(): void
    {
        foreach ([false, true] as $draftIdentity) {
            foreach ([false, true] as $reverse) {
                $sources = [
                    ['code' => 'a', 'parent_code' => null, 'label' => 'A', 'sort_order' => $reverse ? 1 : 0],
                    ['code' => 'b', 'parent_code' => null, 'label' => 'B', 'sort_order' => $reverse ? 0 : 1],
                    ['code' => 'new', 'parent_code' => 'a', 'label' => 'Child'],
                    ['code' => 'owned', 'parent_code' => 'b', 'label' => 'Child'],
                ];
                $database = ['a' => 10, 'b' => 11];
                $draft = [];
                if ($draftIdentity) {
                    $draft[] = ['ergonode_code' => 'owned', 'magento_category_id' => 12];
                } else {
                    $database['owned'] = 12;
                }
                $result = $this->resolve($sources, $this->magento([
                    10 => [2, 'A'], 11 => [2, 'B'], 12 => [10, 'Child'],
                ]), $database, $draft);

                self::assertNull($result['assignments']['new']['magento_category_id']);
                self::assertSame(12, $result['assignments']['owned']['magento_category_id']);
                self::assertSame($draftIdentity ? 'draft' : 'database', $result['assignments']['owned']['source']);
                self::assertSame(11, $result['assignments']['owned']['expected_parent_id']);
                self::assertSame([], $result['conflicts']);
            }
        }
    }

    public function testNumericCodesPreserveIdentityAndZeroParent(): void
    {
        foreach ([false, true] as $stored) {
            $result = $this->resolve([
                ['code' => '0', 'parent_code' => null, 'label' => 'Parent'],
                ['code' => '123', 'parent_code' => '0', 'label' => 'Child'],
                ['code' => '001', 'parent_code' => '0', 'label' => 'Leading zero'],
            ], $this->magento([
                10 => [2, 'Parent'], 11 => [10, 'Child'], 12 => [10, 'Leading zero'],
            ]), $stored ? ['0' => 10, '123' => 11, '001' => 12] : []);
            self::assertSame(10, $result['assignments']['0']['magento_category_id']);
            self::assertSame(11, $result['assignments']['123']['magento_category_id']);
            self::assertSame(12, $result['assignments']['001']['magento_category_id']);
            self::assertSame(10, $result['assignments']['123']['expected_parent_id']);
            self::assertSame([], $result['conflicts']);
        }
    }

    public function testUnmappedExcludedBranchPreservesDescendantIdentitiesWithoutConflicts(): void
    {
        foreach ([true, false] as $childActive) {
            $result = $this->resolve([
                ['code' => '0', 'parent_code' => null, 'label' => 'Excluded', 'active' => false],
                ['code' => '123', 'parent_code' => '0', 'label' => 'Child', 'active' => $childActive],
                ['code' => 'leaf', 'parent_code' => '123', 'label' => 'Leaf'],
                ['code' => 'free', 'parent_code' => null, 'label' => 'Other'],
            ], $this->magento([10 => [2, 'Child'], 11 => [10, 'Other']]), ['123' => 10]);

            self::assertSame([], $result['conflicts']);
            foreach (['0', '123', 'leaf'] as $code) {
                self::assertSame('excluded', $result['assignments'][$code]['source']);
            }
            self::assertSame(10, $result['assignments']['123']['magento_category_id']);
            self::assertContains(10, $result['consumed_magento_ids']);
            self::assertEqualsCanonicalizing([10, 11], $result['protected_magento_ids']);
            self::assertNull($result['assignments']['free']['magento_category_id']);
        }
    }

    public function testExcludedTargetProtectsSourceDescendantsAcrossUnmappedNodes(): void
    {
        foreach ([false, true] as $mappedLeaf) {
            $result = $this->resolve([
                ['code' => 'p', 'parent_code' => null, 'label' => 'Parent'],
                ['code' => '0', 'parent_code' => 'p', 'label' => 'Unmapped'],
                ['code' => '123', 'parent_code' => '0', 'label' => 'Leaf'],
                ['code' => 'free', 'parent_code' => null, 'label' => 'Other'],
            ], $this->magento([
                10 => [2, 'Parent', false], 20 => [2, 'Leaf'], 21 => [20, 'Other'],
            ]), $mappedLeaf ? ['p' => 10, '123' => 20] : ['p' => 10]);

            self::assertSame([], $result['conflicts']);
            foreach (['p', '0', '123'] as $code) {
                self::assertSame('excluded', $result['assignments'][$code]['source']);
            }
            self::assertSame($mappedLeaf ? 20 : null, $result['assignments']['123']['magento_category_id']);
            if ($mappedLeaf) {
                self::assertContains(20, $result['consumed_magento_ids']);
                self::assertContains(21, $result['protected_magento_ids']);
            }
        }
    }

    public function testProtectionReachesMappedBranchesBeforeNameMatchingRegardlessOfOrder(): void
    {
        foreach ([false, true] as $reverse) {
            $sources = [
                ['code' => 'p', 'parent_code' => null, 'label' => 'Parent'],
                ['code' => 'gap', 'parent_code' => 'p', 'label' => 'Gap'],
                ['code' => 'leaf', 'parent_code' => 'gap', 'label' => 'Leaf'],
                ['code' => 'other', 'parent_code' => null, 'label' => 'Other'],
                ['code' => 'nested', 'parent_code' => 'other', 'label' => 'Nested'],
                ['code' => 'free', 'parent_code' => null, 'label' => 'Free'],
            ];
            $result = $this->resolve($reverse ? array_reverse($sources) : $sources, $this->magento([
                10 => [2, 'Parent', false], 20 => [2, 'Leaf'], 21 => [20, 'Other'],
                30 => [2, 'Nested'], 31 => [30, 'Free'],
            ]), ['p' => 10, 'leaf' => 20, 'other' => 21, 'nested' => 30]);

            self::assertSame([], $result['conflicts']);
            foreach (['p', 'gap', 'leaf', 'other', 'nested'] as $code) {
                self::assertSame('excluded', $result['assignments'][$code]['source']);
            }
            self::assertContains(31, $result['protected_magento_ids']);
            self::assertNull($result['assignments']['free']['magento_category_id']);
        }
    }

    public function testExcludedMagentoRootProtectsUnmappedSourceTree(): void
    {
        $magento = $this->magento([]);
        $magento[2]['active'] = false;
        $result = $this->resolve([
            ['code' => 'p', 'parent_code' => null, 'label' => 'Parent'],
            ['code' => 'child', 'parent_code' => 'p', 'label' => 'Child'],
        ], $magento);
        self::assertSame([], $result['conflicts']);
        self::assertSame('excluded', $result['assignments']['child']['source']);
    }

    public function testActiveOrphansAndCyclesStillReportConflicts(): void
    {
        $result = $this->resolve([
            ['code' => 'orphan', 'parent_code' => 'missing', 'label' => 'Orphan'],
            ['code' => 'a', 'parent_code' => 'b', 'label' => 'A'],
            ['code' => 'b', 'parent_code' => 'a', 'label' => 'B'],
        ], $this->magento([]));

        self::assertCount(3, $result['conflicts']);
        foreach ($result['assignments'] as $assignment) {
            self::assertSame('unmatched', $assignment['source']);
            self::assertNull($assignment['expected_parent_id']);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @param array<int, array<string, mixed>> $magento
     * @param array<string, int> $database
     * @param array<int, array{ergonode_code: string, magento_category_id: int}> $draft
     * @return array<string, mixed>
     */
    private function resolve(array $sources, array $magento, array $database = [], array $draft = []): array
    {
        return (new CategoryIdentityResolver(
            new CategoryNameNormalizer(),
            new CategoryDeletionCandidateResolver()
        ))->resolve(
            2,
            $sources,
            $magento,
            $database,
            $draft
        );
    }

    /** @param string[] $codes @return array<int, array<string, mixed>> */
    private function sources(array $codes, ?string $fixedLabel = null): array
    {
        return array_map(
            static fn (string $code, int $index): array => [
                'code' => $code,
                'parent_code' => null,
                'label' => $fixedLabel ?? ucfirst($code),
                'sort_order' => $index,
                'active' => true,
            ],
            $codes,
            array_keys($codes)
        );
    }

    /**
     * @param array<int, array{int, string, 2?: bool}> $definitions
     * @return array<int, array<string, mixed>>
     */
    private function magento(array $definitions): array
    {
        $result = [2 => [
            'id' => 2, 'parent_id' => 1, 'label' => 'Root', 'position' => 0,
            'level' => 1, 'path' => '1/2', 'url_key' => 'root', 'active' => true,
        ]];
        foreach ($definitions as $id => $definition) {
            [$parentId, $label] = $definition;
            $result[$id] = [
                'id' => $id,
                'parent_id' => $parentId,
                'label' => $label,
                'position' => count($result),
                'level' => 2,
                'path' => '1/2/' . $id,
                'url_key' => strtolower(str_replace(' ', '-', $label)),
                'active' => $definition[2] ?? true,
            ];
        }

        return $result;
    }

    public function testStoredIdentityCannotMoveAnExcludedTargetOrItsChild(): void
    {
        foreach ([10, 11] as $targetId) {
            $result = $this->resolve(
                $this->sources(['one']),
                $this->magento([10 => [2, 'Excluded', false], 11 => [10, 'Child']]),
                ['one' => $targetId]
            );
            self::assertSame('excluded', $result['assignments']['one']['source']);
            self::assertSame($targetId, $result['assignments']['one']['magento_category_id']);
            self::assertContains($targetId, $result['consumed_magento_ids']);
            self::assertSame([], $result['conflicts']);
        }
    }
}
