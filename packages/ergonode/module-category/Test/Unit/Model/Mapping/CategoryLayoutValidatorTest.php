<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Mapping;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Mapping\CategoryLayoutValidator;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryLayoutValidatorTest extends TestCase
{
    public static function invalidDrafts(): array
    {
        return [
            'duplicate codes' => [[['code' => 'a'], ['code' => 'a']], 'unique'],
            'duplicate target' => [[['code' => 'a', 'magento_category_id' => 3],
                ['code' => 'b', 'magento_category_id' => 3]], 'mapped more than once'],
            'cycle through new drafts' => [[['code' => 'a', 'parent_code' => 'b'],
                ['code' => 'b', 'parent_code' => 'a']], 'cycle'],
            'missing parent' => [[['code' => 'a', 'parent_code' => 'missing']], 'Invalid parent'],
        ];
    }

    #[DataProvider('invalidDrafts')]
    public function testRejectsInvalidDraftBeforeAnyPublication(array $items, string $message): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($message);
        $this->validator()->validate(7, $items);
    }

    public function testChecksTargetsAgainstUntouchedMappings(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('mapped more than once');
        $this->validator(['existing' => ['magento_category_id' => 3]])
            ->validate(7, [['code' => 'new', 'magento_category_id' => 3]]);
    }

    public function testAcceptsParentFromEarlierPreparedBatchWithoutSnapshot(): void
    {
        $validator = $this->validator([], [['code' => 'parent', 'parent_code' => null, 'magento_category_id' => 3]]);
        $validator->validate(7, [['code' => 'child', 'parent_code' => 'parent', 'magento_category_id' => 4]]);
        $this->addToAssertionCount(1);
    }

    public function testRejectsMagentoCategoryOutsideConfiguredRoot(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('configured root tree');
        $this->validator(validTargets: false)->validate(7, [['code' => 'a', 'magento_category_id' => 999]]);
    }

    public function testValidatesLongDraftTreeWithoutRepeatedMagentoLookups(): void
    {
        $items = [];
        for ($i = 0; $i < 1000; $i++) {
            $items[] = ['code' => 'code-' . $i, 'parent_code' => $i ? 'code-' . ($i - 1) : null];
        }
        $magento = $this->createMock(MagentoCategoryProvider::class);
        $magento->expects(self::never())->method('getIds');
        $this->validator(magento: $magento)->validate(7, $items);
    }

    public function testReadsMembershipOnceForMappedTreeAndRefreshesItOnNextValidation(): void
    {
        $magento = $this->createMock(MagentoCategoryProvider::class);
        $magento->expects(self::exactly(2))->method('getIds')->with(2)->willReturn([3, 4], [3]);
        $magento->expects(self::never())->method('getCategories');
        $magento->expects(self::never())->method('exists');
        $validator = $this->validator(magento: $magento);
        $items = [['code' => 'a', 'magento_category_id' => 3],
            ['code' => 'b', 'parent_code' => 'a', 'magento_category_id' => 4]];
        $validator->validate(7, $items);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('configured root tree');
        $validator->validate(7, $items);
    }

    private function validator(
        array $existing = [],
        array $prepared = [],
        bool $validTargets = true,
        ?MagentoCategoryProvider $magento = null
    ): CategoryLayoutValidator {
        $query = $this->createStub(CategoryTreeQuery::class);
        $query->method('getById')->willReturn(['root_category_id' => 2]);
        $cache = $this->createStub(CategoryCacheProvider::class);
        $cache->method('getRowsByCode')->willReturn($existing);
        $mappings = $this->createStub(CategoryMappingQuery::class);
        $mappings->method('getPreparedLayoutByTreeId')->willReturn($prepared);
        if ($magento === null) {
            $magento = $this->createStub(MagentoCategoryProvider::class);
            $magento->method('getIds')->willReturn($validTargets ? [3, 4] : []);
        }
        return new CategoryLayoutValidator(
            $query,
            $this->createStub(CategoryTreeSourceState::class),
            $cache,
            $magento,
            $mappings
        );
    }
}
