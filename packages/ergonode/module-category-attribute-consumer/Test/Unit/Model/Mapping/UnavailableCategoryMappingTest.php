<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeMappingProvider;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeValueMappingFactory;
use Ergonode\CategoryAttributeConsumer\Model\Provider\ErgonodeCategoryAttributeProvider;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\MappingStateBuilderInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use PHPUnit\Framework\TestCase;

class UnavailableCategoryMappingTest extends TestCase
{
    public function testStreamsLabelsIntoEveryEligibleMappingWithoutBulkDefinitions(): void
    {
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRows')->willReturn(array_map(static fn (int $id): array => [
            'mapping_id' => $id, 'ergonode_attribute_code' => 'color', 'ergonode_type' => 'select',
            'magento_attribute_code' => 'target_' . $id, 'status' => 'complete',
        ], [1, 2]));
        $reader->method('getCompleteOptionRows')->willReturn([]);
        $source = $this->createStub(ErgonodeCategoryAttributeProvider::class);
        $source->method('isAvailableForSynchronization')->willReturn(true);
        $policy = $this->createStub(CategoryAttributePolicy::class);
        $policy->method('isMappable')->willReturn(true);
        $options = $this->createMock(ErgonodeOptionProviderInterface::class);
        $options->expects(self::never())->method('getOptionDefinitionsByCodes');
        $options->expects(self::once())->method('iterateOptionDefinitions')->willReturnCallback(
            static function (): iterable {
                yield ['attribute_code' => 'color', 'code' => 'red', 'sort_order' => 1,
                    'labels' => ['en_US' => 'Red']];
            }
        );
        $factory = $this->createStub(CategoryAttributeValueMappingFactory::class);
        $factory->method('create')->willReturn(['option_labels' => [], 'option_ids' => []]);
        $provider = new CategoryAttributeMappingProvider(
            $reader,
            $this->createStub(MappingStateBuilderInterface::class),
            $options,
            $source,
            $this->createStub(MagentoAttributeProviderInterface::class),
            $this->createStub(AttributeTypeCompatibilityInterface::class),
            $policy,
            $factory
        );
        $mappings = $provider->getValueMappings();
        self::assertCount(2, $mappings);
        foreach ($mappings as $mapping) {
            self::assertSame(['red' => ['en_US' => 'Red']], $mapping['option_labels']);
        }
    }

    public function testDetachedOrDeletedSourceDoesNotParticipateInValueSynchronization(): void
    {
        $reader = $this->createMock(MappingReaderInterface::class);
        $reader->method('getAttributeRows')->willReturn([[
            'mapping_id' => 10, 'ergonode_attribute_code' => 'detached',
            'magento_attribute_code' => 'description', 'status' => 'complete',
        ]]);
        $reader->expects(self::never())->method('getCompleteOptionRows');
        $source = $this->createStub(ErgonodeCategoryAttributeProvider::class);
        $source->method('isAvailableForSynchronization')->with('detached')->willReturn(false);
        $factory = $this->createMock(CategoryAttributeValueMappingFactory::class);
        $factory->expects(self::never())->method('create');
        $provider = new CategoryAttributeMappingProvider(
            $reader,
            $this->createStub(MappingStateBuilderInterface::class),
            $this->createStub(ErgonodeOptionProviderInterface::class),
            $source,
            $this->createStub(MagentoAttributeProviderInterface::class),
            $this->createStub(AttributeTypeCompatibilityInterface::class),
            $this->createStub(CategoryAttributePolicy::class),
            $factory
        );

        self::assertSame([], $provider->getValueMappings());
    }
}
