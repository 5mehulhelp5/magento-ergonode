<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model;

use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryOptionAutoMatcherInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class OptionSuggestionsTest extends TestCase
{
    public function testConfiguredConsumerSuggestsWithoutChangingPersistedMappings(): void
    {
        $objects = Bootstrap::getObjectManager();
        $objects->get(AttributeMappingWriterInterface::class)->save([[
            'left' => ['code' => 'test_category_color', 'type' => 'select'],
            'right' => ['code' => 'test_category_color', 'type' => 'select'],
        ]], []);
        $reader = $objects->get(MappingReaderInterface::class);
        $before = $reader->getAttributeRows();
        $id = (int)$before[0]['mapping_id'];
        $optionsBefore = $reader->getOptionRows($id);
        $result = $objects->get(CategoryOptionAutoMatcherInterface::class)->suggest($id, [
            ['code' => 'navy', 'label' => 'Granatowy'],
        ], [
            ['code' => 'option_42', 'label' => 'Navy'],
        ]);

        self::assertCount(1, $result['matches']);
        self::assertSame('option_42', $result['matches'][0]['right']['code']);
        self::assertSame($before, $reader->getAttributeRows());
        self::assertSame($optionsBefore, $reader->getOptionRows($id));
    }
}
