<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Model\Sync\CategoryCreator;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\State;
use Magento\Framework\Filter\TranslitUrl;
use PHPUnit\Framework\TestCase;

class CategoryCreatorTest extends TestCase
{
    public function testUsesNameOnlyForUrlKeyAndAppliesResolvedCreationValues(): void
    {
        $category = $this->createMock(Category::class);
        foreach ([
            'setStoreId', 'setName',
        ] as $method) {
            $category->method($method)->willReturnSelf();
        }
        $category->expects(self::once())->method('setData')->with('url_key', 'chairs')->willReturnSelf();
        $category->expects(self::once())->method('setIsActive')->with(1)->willReturnSelf();
        $category->expects(self::once())->method('setIncludeInMenu')->with(0)->willReturnSelf();
        $category->expects(self::once())->method('setParentId')->with(2)->willReturnSelf();
        $category->expects(self::never())->method('setPath');
        $category->expects(self::never())->method('setLevel');
        $savedCategory = $this->createStub(Category::class);
        $savedCategory->method('getId')->willReturn(21);
        $savedCategory->method('getParentId')->willReturn(2);
        $savedCategory->method('getName')->willReturn('Chairs');
        $savedCategory->method('getPath')->willReturn('1/2/21');
        $savedCategory->method('getLevel')->willReturn(2);
        $savedCategory->method('getPosition')->willReturn(1);
        $urlKey = $this->createStub(AttributeInterface::class);
        $urlKey->method('getValue')->willReturn('chairs-21');
        $savedCategory->method('getCustomAttribute')->willReturn($urlKey);
        $factory = $this->createStub(CategoryFactory::class);
        $factory->method('create')->willReturn($category);
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects(self::never())->method('get');
        $repository->expects(self::once())->method('save')->with($category)->willReturn($savedCategory);
        $translit = $this->createStub(TranslitUrl::class);
        $translit->method('filter')->willReturn('chairs');
        $state = $this->createStub(State::class);
        $state->method('emulateAreaCode')->willReturnCallback(
            static fn (string $areaCode, callable $callback): array => $callback()
        );
        $languages = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $languages->expects(self::once())->method('requireAdminLanguageCode');
        $result = (new CategoryCreator(
            $factory,
            $repository,
            $translit,
            $state,
            $languages
        ))->create('Chairs', 2, ['include_in_menu' => 0, 'is_active' => 1]);

        self::assertSame([
            'id' => 21,
            'parent_id' => 2,
            'label' => 'Chairs',
            'path' => '1/2/21',
            'level' => 2,
            'position' => 1,
            'url_key' => 'chairs-21',
        ], $result);
    }
}
