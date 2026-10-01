<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Test\Unit\Model\GraphQl;

use Ergonode\CategoryPublisher\Model\Data\CategoryStateDto;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use PHPUnit\Framework\TestCase;

class CategoryMutationFactoryTest extends TestCase
{
    public function testBuildsCategoryCreateWithoutAttributePayload(): void
    {
        $operation = (new CategoryMutationFactory())->create(
            new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła'])
        );

        self::assertSame('categoryCreate', $operation->getField());
        self::assertSame([
            'code' => 'chairs',
            'name' => [['language' => 'pl_PL', 'value' => 'Krzesła']],
        ], $operation->getVariables()['input']->getValue());
    }

    public function testBuildsNameAndDeleteMutations(): void
    {
        $factory = new CategoryMutationFactory();

        self::assertSame(
            'categorySetName',
            $factory->setName(new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']))->getField()
        );
        self::assertSame('categoryDelete', $factory->delete('chairs')->getField());
    }
}
