<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Model;

use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProvider;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProviderFactory;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\ProductAttributeHistory\Model\MagentoOptionsFactory;
use Ergonode\ProductAttributeHistory\Model\OptionSnapshotProvider;
use PHPUnit\Framework\TestCase;

class OptionSnapshotProviderTest extends TestCase
{
    public function testOptionsRemainScopedAndKeepUnmappedAndMissingSides(): void
    {
        $sourceOption = ['code' => 'yes', 'label' => 'Yes', 'scope' => 'en_US', 'type' => 'option', 'active' => false];
        $targetOption = ['code' => 'option_1', 'label' => 'Yes', 'scope' => 'VALUE 1', 'type' => 'option'];
        $source = $this->createStub(ErgonodeOptionProvider::class);
        $source->method('getOptions')->willReturn([$sourceOption]);
        $target = $this->createStub(MagentoOptionProvider::class);
        $target->method('getOptions')->willReturn([$targetOption]);
        $sourceCaches = [];
        $sourceFactory = $this->createMock(ErgonodeOptionProviderFactory::class);
        $sourceFactory->expects(self::exactly(2))->method('create')
            ->willReturnCallback(static function (array $arguments) use (
                $source,
                &$sourceCaches
            ): ErgonodeOptionProvider {
                self::assertInstanceOf(OptionSnapshotCache::class, $arguments['snapshotCache'] ?? null);
                $sourceCaches[] = $arguments['snapshotCache'];
                return $source;
            });
        $targetFactory = $this->createMock(MagentoOptionsFactory::class);
        $targetFactory->expects(self::exactly(2))->method('create')->willReturn($target);
        $builder = $this->createStub(MappingStateBuilder::class);
        $builder->method('optionContexts')->willReturn([[
            'mapping_id' => 7, 'left' => ['code' => 'first'], 'right' => ['code' => 'target'],
        ]]);
        $builder->method('options')->willReturn([
            ['left' => $sourceOption, 'right' => $targetOption],
            ['left' => ['code' => 'missing', 'label' => 'missing', 'scope' => 'missing', 'type' => 'option'],
                'right' => null],
        ]);
        $reader = $this->createMock(MappingReaderInterface::class);
        $reader->expects(self::exactly(2))->method('getOptionRows')->with(7)->willReturn([]);
        $provider = new OptionSnapshotProvider($sourceFactory, $targetFactory, $reader, $builder);
        $state = $provider->getState(['first' => [], 'second' => []], ['target' => [], 'other' => []], []);
        self::assertSame(
            $state,
            $provider->getState(['first' => [], 'second' => []], ['target' => [], 'other' => []], [])
        );
        self::assertNotSame($sourceCaches[0], $sourceCaches[1]);
        $first = array_column($state['source']['first'], null, 'code');
        self::assertSame('option_1', $first['yes']['mapped_code']);
        self::assertSame('target', $first['yes']['mapped_attribute_code']);
        self::assertFalse($first['yes']['active']);
        self::assertNull($first['missing']['mapped_code']);
        self::assertNull($state['source']['second'][0]['mapped_code']);
        self::assertNull($state['target']['other'][0]['mapped_code']);
        self::assertSame('first', $state['target']['target'][0]['mapped_attribute_code']);
    }
}
