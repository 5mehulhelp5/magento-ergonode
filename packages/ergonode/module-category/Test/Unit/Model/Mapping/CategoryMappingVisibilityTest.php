<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Mapping;

use Ergonode\Category\Model\Mapping\CategoryMappingVisibility;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use PHPUnit\Framework\TestCase;

class CategoryMappingVisibilityTest extends TestCase
{
    public function testVisibilityIsSavedForBothSourcesWithinCategoryTree(): void
    {
        $saver = $this->createMock(MappingVisibilitySaverInterface::class);
        $saver->expects(self::once())->method('saveMany')->with([
            [
                'entity_type' => 'category',
                'source' => 'ergo',
                'parent_identifier' => '7',
                'identifier' => 'chairs',
                'active' => false,
            ],
            [
                'entity_type' => 'category',
                'source' => 'magento',
                'parent_identifier' => '7',
                'identifier' => '12',
                'active' => true,
            ],
        ]);
        $visibility = new CategoryMappingVisibility($saver);

        $visibility->save(7, [
            ['source' => 'ergo', 'code' => 'chairs', 'active' => false],
            ['source' => 'magento', 'code' => '12', 'active' => true],
        ]);
    }
}
