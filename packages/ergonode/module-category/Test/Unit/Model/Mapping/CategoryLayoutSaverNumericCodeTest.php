<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Mapping;

use Ergonode\Category\Api\CategoryMappingSaveHandlerInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;
use Ergonode\Category\Model\Mapping\CategoryLayoutValidator;
use Ergonode\Category\Model\Mapping\CategoryMappingVisibility;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CategoryLayoutSaverNumericCodeTest extends TestCase
{
    public static function numericCodes(): array
    {
        return ['zero' => ['0'], 'numeric' => ['123'], 'leading-zero' => ['001']];
    }

    #[DataProvider('numericCodes')]
    public function testPassesNormalizedCodeAsStringToMappingWriter(string $code): void
    {
        $items = [['code' => $code, 'parent_code' => null, 'sort_order' => 0, 'magento_category_id' => 11]];
        $validator = $this->createMock(CategoryLayoutValidator::class);
        $validator->expects(self::once())->method('validate')->with(7, $items);
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn(['tree_code' => 'test', 'root_category_id' => 2]);
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->method('getRowsByCode')->willReturn([$code => ['magento_category_id' => null]]);
        $cache->expects(self::once())->method('clearCache');
        $mappingWriter = $this->createMock(CategoryMappingWriter::class);
        $mappingWriter->expects(self::once())->method('saveLayout')->with(7, $code, null, 0, 11);
        $visibility = $this->createMock(CategoryMappingVisibility::class);
        $visibility->expects(self::once())->method('save')->with(7, []);
        $handler = $this->createMock(CategoryMappingSaveHandlerInterface::class);
        $handler->expects(self::once())->method('save')->willReturnCallback(
            static function (int $treeId, callable $saveLayout, array $newMappings) use ($code): array {
                self::assertSame(7, $treeId);
                self::assertSame([$code => 11], $newMappings);
                return $saveLayout();
            }
        );

        $saver = new CategoryLayoutSaver(
            $cache,
            $validator,
            $this->createStub(LoggerInterface::class),
            $mappingWriter,
            $treeQuery,
            $visibility,
            $handler
        );
        self::assertSame(['updated' => 1, 'unchanged' => 0, 'attribute_values' => 0], $saver->save(7, $items));
    }
}
