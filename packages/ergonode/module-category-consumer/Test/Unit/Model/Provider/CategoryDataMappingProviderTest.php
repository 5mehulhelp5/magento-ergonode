<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Provider;

use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use PHPUnit\Framework\TestCase;

class CategoryDataMappingProviderTest extends TestCase
{
    public function testSkipsMissingCategoriesAndExcludedAncestorsOnBothSides(): void
    {
        $mappings = $this->createStub(CategoryMappingQuery::class);
        $mappings->method('getValidMappingsByCodes')->willReturn([
            'visible' => [['category_tree_id' => 7, 'magento_category_id' => 10]],
            'missing' => [['category_tree_id' => 7, 'magento_category_id' => 11]],
            'source-child' => [['category_tree_id' => 7, 'magento_category_id' => 12]],
            'target-child' => [['category_tree_id' => 7, 'magento_category_id' => 14]],
        ]);
        $state = $this->createMock(CategoryTreeStateProviderInterface::class);
        $state->expects(self::once())->method('getState')->with(7)->willReturn([
            'tree' => ['is_active' => true, 'root_category_id' => 2],
            'source' => [
                ['identifier' => 'visible', 'active' => true, 'magento_category_id' => 10],
                ['identifier' => 'excluded', 'active' => false],
                ['identifier' => 'source-child', 'active' => true, 'magento_category_id' => 12,
                    'parent_identifier' => 'excluded'],
                ['identifier' => 'target-child', 'active' => true, 'magento_category_id' => 14],
            ],
            'target' => [
                ['identifier' => '10', 'active' => true],
                ['identifier' => '12', 'active' => true],
                ['identifier' => '13', 'active' => false],
                ['identifier' => '14', 'active' => true, 'parent_identifier' => '13'],
            ],
        ]);

        self::assertSame(
            ['visible' => [['category_tree_id' => 7, 'magento_category_id' => 10]]],
            (new CategoryDataMappingProvider($mappings, $state))
                ->getValidMappingsByCodes(['visible', 'missing', 'source-child', 'target-child'])
        );
    }
    public function testNumericCodesRemainUsableAndRespectZeroParentExclusion(): void
    {
        foreach ([true, false] as $included) {
            $rows = [];
            $source = [];
            $target = [];
            foreach (['0', '123', '001'] as $index => $code) {
                $id = 10 + $index;
                $rows[$code] = [['category_tree_id' => 7, 'magento_category_id' => $id]];
                $source[] = ['identifier' => $code, 'magento_category_id' => $id,
                    'active' => $code !== '0' || $included, 'parent_identifier' => $code === '0' ? null : '0'];
                $target[] = ['identifier' => (string)$id, 'active' => true];
            }
            $query = $this->createStub(CategoryMappingQuery::class);
            $query->method('getValidMappingsByCodes')->willReturn($rows);
            $state = $this->createStub(CategoryTreeStateProviderInterface::class);
            $state->method('getState')->willReturn([
                'tree' => ['is_active' => true, 'root_category_id' => 2], 'source' => $source, 'target' => $target,
            ]);
            self::assertSame($included ? $rows : [], (new CategoryDataMappingProvider($query, $state))
                ->getValidMappingsByCodes(['0', '123', '001']));
        }
    }

    public function testClearRebuildsCrossTreeProtectionWithoutChangingRawVisibility(): void
    {
        $mapping = ['B' => [['category_tree_id' => 7, 'magento_category_id' => 11]]];
        $query = $this->createStub(CategoryMappingQuery::class);
        $query->method('getValidMappingsByCodes')->willReturn($mapping);
        $state = ['tree' => ['is_active' => true, 'root_category_id' => 2], 'source' => [
            ['identifier' => 'A', 'active' => false, 'magento_category_id' => 10],
            ['identifier' => 'B', 'active' => true, 'magento_category_id' => 11],
        ], 'target' => [
            ['identifier' => '10', 'active' => true],
            ['identifier' => '11', 'active' => true, 'parent_identifier' => '10'],
        ]];
        $enabled = $state;
        $enabled['source'][0]['active'] = true;
        $provider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $provider->expects(self::exactly(2))->method('getState')->willReturnOnConsecutiveCalls($state, $enabled);
        $subject = new CategoryDataMappingProvider($query, $provider);
        self::assertSame([], $subject->getValidMappingsByCodes(['B']));
        self::assertSame([], $subject->getValidMappingsByCodes(['B']));
        $subject->clear();
        self::assertSame($mapping, $subject->getValidMappingsByCodes(['B']));
        self::assertTrue($state['source'][1]['active']);
        self::assertTrue($state['target'][1]['active']);
    }

    public function testMissingAndCyclicAncestorsRemainIneligible(): void
    {
        foreach (['missing', 'cycle'] as $variant) {
            $query = $this->createStub(CategoryMappingQuery::class);
            $query->method('getValidMappingsByCodes')->willReturn([
                'A' => [['category_tree_id' => 7, 'magento_category_id' => 10]],
            ]);
            $provider = $this->createStub(CategoryTreeStateProviderInterface::class);
            $provider->method('getState')->willReturn([
                'tree' => ['is_active' => true, 'root_category_id' => 2],
                'source' => [['identifier' => 'A', 'active' => true, 'magento_category_id' => 10,
                    'parent_identifier' => $variant === 'cycle' ? 'A' : 'absent']],
                'target' => [['identifier' => '10', 'active' => true]],
            ]);
            self::assertSame([], (new CategoryDataMappingProvider($query, $provider))->getValidMappingsByCodes(['A']));
        }
    }

    public function testManualSaveUsesFreshTreeStateWithoutStreamActivityOrCachedProtection(): void
    {
        $query = $this->createStub(CategoryMappingQuery::class);
        $query->method('getValidMappingsByCodes')->willReturn([
            '0' => [['category_tree_id' => 7, 'magento_category_id' => 10]],
        ]);
        $state = ['tree' => ['is_active' => true, 'root_category_id' => 2], 'source' => [
            ['identifier' => '0', 'active' => true, 'magento_category_id' => 10],
            ['identifier' => '001', 'active' => true, 'magento_category_id' => 11],
        ], 'target' => [
            ['identifier' => '10', 'active' => true],
            ['identifier' => '11', 'active' => true, 'parent_identifier' => '10'],
        ]];
        $excluded = $state;
        $excluded['tree']['is_active'] = false;
        $excluded['source'][0]['active'] = false;
        $enabled = $state;
        $enabled['tree']['is_active'] = false;
        $provider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $provider->expects(self::exactly(3))->method('getState')->with(7)
            ->willReturnOnConsecutiveCalls($state, $excluded, $enabled);
        $subject = new CategoryDataMappingProvider($query, $provider);
        self::assertNotEmpty($subject->getValidMappingsByCodes(['0']));
        $operations = [
            ['category_id' => 10, 'entity' => ['code' => '0']],
            ['category_id' => 11, 'entity' => ['code' => '001']],
        ];
        self::assertSame([], $subject->filterForTree(7, $operations));
        self::assertSame($operations, $subject->filterForTree(7, [
            ...$operations,
            ['category_id' => 12, 'entity' => ['code' => '0']],
            ['category_id' => 10, 'entity' => ['code' => 'absent']],
        ]));
        self::assertSame([], $subject->filterForTree(7, []));
    }
}
