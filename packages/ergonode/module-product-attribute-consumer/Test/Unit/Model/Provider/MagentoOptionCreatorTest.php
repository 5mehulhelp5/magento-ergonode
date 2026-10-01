<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Provider;

use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoOptionCreator;
use Ergonode\ProductAttribute\Model\Provider\MagentoVisibilityOptionProvider;
use Magento\Catalog\Api\ProductAttributeOptionManagementInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class MagentoOptionCreatorTest extends TestCase
{
    public function testDuplicateExistingLabelsRequireManualChoice(): void
    {
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getFrontendInput')->willReturn('select');
        $repository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->willReturn($attribute);
        $first = $this->createStub(AttributeOptionInterface::class);
        $first->method('getValue')->willReturn('10');
        $first->method('getLabel')->willReturn('Blue');
        $second = $this->createStub(AttributeOptionInterface::class);
        $second->method('getValue')->willReturn('11');
        $second->method('getLabel')->willReturn(' blue ');
        $management = $this->createStub(ProductAttributeOptionManagementInterface::class);
        $management->method('getItems')->willReturn([$first, $second]);
        $creator = new MagentoOptionCreator(
            $repository,
            $management,
            $this->createStub(AttributeOptionInterfaceFactory::class),
            $this->createStub(MagentoVisibilityOptionProvider::class)
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('select the option manually');

        $creator->create('color', 'Blue');
    }

    public function testCreatesOptionWithRequestedPositionAndReturnsNumericId(): void
    {
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getFrontendInput')->willReturn('select');
        $attributeRepository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $attributeRepository->expects(self::once())->method('get')->with('color')->willReturn($attribute);
        $option = $this->createMock(AttributeOptionInterface::class);
        $option->expects(self::once())->method('setLabel')->with('Blue')->willReturnSelf();
        $option->expects(self::once())->method('setSortOrder')->with(3)->willReturnSelf();
        $created = $this->createStub(AttributeOptionInterface::class);
        $created->method('getValue')->willReturn('27');
        $created->method('getLabel')->willReturn('Blue');
        $optionManagement = $this->createMock(ProductAttributeOptionManagementInterface::class);
        $optionManagement->expects(self::exactly(2))
            ->method('getItems')
            ->with('color')
            ->willReturnOnConsecutiveCalls([], [$created]);
        $optionManagement->expects(self::once())->method('add')->with('color', $option)->willReturn('27');
        $optionFactory = $this->createMock(AttributeOptionInterfaceFactory::class);
        $optionFactory->expects(self::once())->method('create')->willReturn($option);
        $visibility = $this->createStub(MagentoVisibilityOptionProvider::class);
        $visibility->method('isSupported')->willReturn(false);

        $result = (new MagentoOptionCreator(
            $attributeRepository,
            $optionManagement,
            $optionFactory,
            $visibility
        ))->create('color', 'Blue', 3);

        self::assertSame(27, $result['option_id']);
        self::assertSame('option_27', $result['code']);
        self::assertTrue($result['created']);
    }
}
