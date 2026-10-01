<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryCreationConfigurationProvider;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryCreationConfigurationProviderTest extends TestCase
{
    public function testAcceptsCompleteMappingsForRequiredCreationAttributes(): void
    {
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $policy = $this->createStub(CategoryAttributePolicy::class);
        $policy->method('getManualCreationValues')->willReturn([]);
        $policy->method('getMappedCreationAttributeCodes')->willReturn(['is_active', 'include_in_menu']);
        $mappings = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappings->method('getValueMappings')->willReturn([
            ['magento_attribute_code' => 'is_active'],
            ['magento_attribute_code' => 'include_in_menu'],
        ]);

        $result = (new CategoryCreationConfigurationProvider($config, $policy, $mappings))->get();

        self::assertTrue($result['attributes_enabled']);
        self::assertSame(['is_active', 'include_in_menu'], $result['mapped_attribute_codes']);
    }

    public function testRejectsIncompleteRequiredMappings(): void
    {
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $policy = $this->createStub(CategoryAttributePolicy::class);
        $policy->method('getMappedCreationAttributeCodes')->willReturn(['is_active', 'include_in_menu']);
        $mappings = $this->createStub(CategoryAttributeMappingProviderInterface::class);
        $mappings->method('getValueMappings')->willReturn([
            ['magento_attribute_code' => 'is_active'],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('include_in_menu');

        (new CategoryCreationConfigurationProvider($config, $policy, $mappings))->get();
    }
}
