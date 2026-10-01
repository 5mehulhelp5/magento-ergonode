<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Api\ValueAdapterInterface;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use PHPUnit\Framework\TestCase;

class ValueAdapterRegistryTest extends TestCase
{
    public function testMissingAdapterBlocksBothDirectionsButLeavesGenericMappingsAvailable(): void
    {
        $registry = new ValueAdapterRegistry();
        foreach (['import', 'publish'] as $direction) {
            self::assertFalse($registry->isAvailable('category_reference', 'category', $direction));
            self::assertTrue($registry->isAvailable(null, 'description', $direction));
        }
    }

    public function testAvailabilityDependsOnDirectionAndCurrentAttributeConfiguration(): void
    {
        $adapter = $this->createStub(ValueAdapterInterface::class);
        $adapter->method('supports')->willReturnCallback(static fn (string $code): bool => $code === 'category');
        $adapter->method('isAvailable')->willReturnCallback(
            static fn (string $direction): bool => $direction === 'import'
        );
        $registry = new ValueAdapterRegistry(['category_reference' => $adapter]);

        self::assertSame('category_reference', $registry->getRequiredCode('category'));
        self::assertTrue($registry->isAvailable('category_reference', 'category', 'import'));
        self::assertFalse($registry->isAvailable('category_reference', 'category', 'publish'));
        self::assertFalse($registry->isAvailable('category_reference', 'previous_category', 'import'));
        self::assertFalse($registry->isAvailable(null, 'category', 'import'));
    }
}
