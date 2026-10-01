<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Test\Unit\Model;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Model\Mapping\MappingStateBuilder;
use PHPUnit\Framework\TestCase;

class MappingStateBuilderTest extends TestCase
{
    public function testMissingSourceAndDraftRetainTheirIdentityWithoutAConsumer(): void
    {
        $types = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $types->method('canMapAttributes')->willReturn(false);
        $state = (new MappingStateBuilder($types))->attributes([
            ['mapping_id' => 7, 'ergonode_attribute_code' => 'removed', 'magento_attribute_code' => 'meta_title'],
            ['mapping_id' => 8, 'ergonode_attribute_code' => 'draft', 'magento_attribute_code' => null],
        ], [], ['meta_title' => ['code' => 'meta_title', 'label' => 'Title', 'type' => 'text']]);

        self::assertSame(7, $state[0]['mapping_id']);
        self::assertSame('removed', $state[0]['left']['code']);
        self::assertSame('missing', $state[0]['left']['scope']);
        self::assertSame('Title', $state[0]['right']['label']);
        self::assertSame('draft', $state[1]['left']['code']);
        self::assertNull($state[1]['right']);
        self::assertSame('warning', $state[1]['tone']);
    }

    public function testOptionZeroAndMissingOptionsRemainVisible(): void
    {
        $state = (new MappingStateBuilder($this->createStub(AttributeTypeCompatibilityInterface::class)))->options([
            ['ergonode_option_code' => 'no', 'magento_option_id' => 0],
        ], [], []);

        self::assertSame('no', $state[0]['left']['code']);
        self::assertSame('option_0', $state[0]['right']['code']);
        self::assertSame('option', $state[0]['right']['type']);
    }
}
