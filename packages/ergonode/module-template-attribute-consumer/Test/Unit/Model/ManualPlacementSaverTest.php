<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model;

use Ergonode\TemplateAttribute\Api\StructureProviderInterface;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementResource;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementSaver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ManualPlacementSaverTest extends TestCase
{
    #[DataProvider('ineligibleAttributes')]
    public function testRejectsIneligibleAttributeWithoutWriting(array $attribute): void
    {
        $provider = $this->createStub(StructureProviderInterface::class);
        $provider->method('get')->willReturn(['attributes' => [$attribute]]);
        $resource = $this->createMock(ManualPlacementResource::class);
        $resource->expects(self::never())->method('save');
        $this->expectException(LocalizedException::class);
        (new ManualPlacementSaver($provider, $resource))->save('shoes', 4, 12, true);
    }

    public static function ineligibleAttributes(): array
    {
        $attribute = ['attribute_id' => 12, 'attribute_group_id' => 3,
            'ergonode_attribute_codes' => ['material'], 'placement_protected' => false];
        return [
            'unmapped' => [array_replace($attribute, ['ergonode_attribute_codes' => []])],
            'outside set' => [array_replace($attribute, ['attribute_group_id' => null])],
            'system protection' => [array_replace($attribute, ['placement_protected' => true])],
            'different attribute' => [array_replace($attribute, ['attribute_id' => 99])],
        ];
    }
}
