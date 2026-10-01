<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryDataWorkProvider;
use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use PHPUnit\Framework\TestCase;

class CategoryDataWorkProviderTest extends TestCase
{
    public function testNoNameDestinationOrValueMappingsMeansNoDataWork(): void
    {
        self::assertFalse($this->provider(null, [])->hasWork());
    }

    public function testDirectNameUpdatesAndAlternativeNameDestinationsRequireData(): void
    {
        self::assertTrue($this->provider('name', [])->hasWork());
        self::assertTrue($this->provider('meta_title', [])->hasWork());
    }

    public function testAttributeMappingsRequireDataWhenDirectNameUpdatesAreDisabled(): void
    {
        self::assertTrue($this->provider(null, [['magento_attribute_code' => 'description']])->hasWork());
        self::assertTrue($this->provider(null, [['magento_attribute_code' => 'name']])->hasWork());
        self::assertTrue($this->provider(null, [['magento_attribute_code' => 'is_active']])->hasWork());
    }

    private function provider(?string $destination, array $mappings): CategoryDataWorkProvider
    {
        $nameTarget = $this->createStub(CategoryNameTargetProviderInterface::class);
        $nameTarget->method('getAttributeCode')->willReturn($destination);
        $mappingProvider = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappingProvider->method('getValueMappings')->willReturn($mappings);

        return new CategoryDataWorkProvider($mappingProvider, $nameTarget);
    }
}
