<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Unit\Model\Publication;

use Ergonode\AttributePublisher\Api\AttributeOptionBatchSynchronizerInterface;
use Ergonode\AttributePublisher\Api\AttributeOptionSynchronizerInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\ProductAttributePublisher\Model\Publication\OptionDefinitionPublisher;
use Ergonode\ProductAttributePublisher\Model\Source\OptionSourceStateBuilder;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use PHPUnit\Framework\TestCase;

class OptionDefinitionPublisherTest extends TestCase
{
    public function testSingleOptionUsesMagentoIdentityAndSharedBuilderInsteadOfSubmittedLabel(): void
    {
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $repository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with('is_enabled')->willReturn($attribute);
        $state = new AttributeOptionState('no', ['en_GB' => 'No', 'pl_PL' => 'Nie']);
        $builder = $this->createMock(OptionSourceStateBuilder::class);
        $builder->expects(self::once())->method('build')->with($attribute, 'enabled', [0])->willReturn([$state]);
        $publisher = new OptionDefinitionPublisher(
            $this->createStub(AttributeOptionSynchronizerInterface::class),
            $this->createStub(AttributeOptionBatchSynchronizerInterface::class),
            $repository,
            $builder,
            $this->createStub(SynchronizationRateLimitGuard::class)
        );

        self::assertSame($state, $publisher->prepareState('is_enabled', 'enabled', [
            'code' => 'option_0', 'label' => 'Any browser label',
        ]));
    }
}
