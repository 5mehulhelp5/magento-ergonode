<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryCreationDataProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreationService;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreator;
use PHPUnit\Framework\TestCase;

class CategoryCreationServiceTest extends TestCase
{
    public function testCreatesCategoryThenWritesItsMappedAttributes(): void
    {
        $entity = [
            'code' => 'chairs',
            'labels' => ['en_US' => 'Chairs'],
            'attributes' => [['code' => 'description', 'type' => 'text', 'values' => ['en_US' => 'Text']]],
            'hash' => 'hash',
            'raw' => [],
        ];
        $category = [
            'id' => 42,
            'parent_id' => 2,
            'label' => 'Chairs',
            'path' => '1/2/42',
            'level' => 2,
            'position' => 1,
            'url_key' => 'chairs',
        ];
        $data = $this->createMock(CategoryCreationDataProviderInterface::class);
        $data->expects(self::once())->method('get')->with('chairs')->willReturn([
            'values' => ['is_active' => 1, 'include_in_menu' => 0],
            'entity' => $entity,
        ]);
        $creator = $this->createMock(CategoryCreator::class);
        $creator->expects(self::once())->method('create')
            ->with('Chairs', 2, ['is_active' => 1, 'include_in_menu' => 0])
            ->willReturn($category);
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronize')->with([[
            'category_id' => 42,
            'entity' => $entity,
        ]]);

        $result = (new CategoryCreationService($data, $creator, $synchronizer))
            ->create('chairs', 'Chairs', 2);

        self::assertSame($category, $result);
    }
}
