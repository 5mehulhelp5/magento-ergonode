<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Api\ValueAdapterInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingNormalizer;
use Ergonode\ProductAttribute\Model\Mapping\ProductAttributeMappingCompatibility;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class AttributeMappingNormalizerTest extends TestCase
{
    public function testAssignsAdapterAndPreservesItWhenModuleDisappearsAndPairChanges(): void
    {
        $adapter = $this->createStub(ValueAdapterInterface::class);
        $adapter->method('supports')->willReturn(true);
        $mapping = [
            'left' => ['code' => 'category', 'type' => 'text'],
            'right' => ['code' => 'default_category', 'type' => 'text'],
        ];
        $original = $this->normalizer(new ValueAdapterRegistry(['category_reference' => $adapter]))
            ->normalize([$mapping]);
        self::assertSame('category_reference', reset($original)['value_adapter']);

        $mapping['left']['code'] = 'new_category';
        $mapping['value_adapter'] = null;
        $saved = $this->normalizer(new ValueAdapterRegistry())->normalize([$mapping], $original);
        self::assertSame('category_reference', reset($saved)['value_adapter']);
        self::assertNotSame(reset($original)['content_hash'], reset($saved)['content_hash']);
    }

    public function testClientCannotAssignAnAdapterToAnOrdinaryAttribute(): void
    {
        $saved = $this->normalizer(new ValueAdapterRegistry())->normalize([[
            'left' => ['code' => 'description', 'type' => 'text'],
            'right' => ['code' => 'description', 'type' => 'text'],
            'value_adapter' => 'category_reference',
        ]]);
        self::assertNull(reset($saved)['value_adapter']);
    }

    private function normalizer(ValueAdapterRegistry $registry): AttributeMappingNormalizer
    {
        $compatibility = $this->createStub(ProductAttributeMappingCompatibility::class);
        $compatibility->method('canMapAttributes')->willReturn(true);
        $policy = $this->createStub(ProductAttributePolicy::class);
        $policy->method('isTypeMappable')->willReturn(true);
        $policy->method('isMappable')->willReturn(true);
        $policy->method('isErgonodeMappable')->willReturn(true);

        return new AttributeMappingNormalizer(new Json(), $compatibility, $policy, $registry);
    }
}
