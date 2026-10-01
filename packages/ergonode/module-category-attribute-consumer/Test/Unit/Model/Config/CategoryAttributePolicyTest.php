<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Config;

use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class CategoryAttributePolicyTest extends TestCase
{
    public function testCombinesDiExclusionsWithConfiguredOwnershipModes(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): string => str_contains($path, 'include_in_menu')
                || str_contains($path, 'name_mode') ? 'manual' : 'mapping'
        );
        $scopeConfig->method('isSetFlag')->willReturn(false);
        $policy = new CategoryAttributePolicy(
            $scopeConfig,
            $this->createStub(CategoryNameTargetProviderInterface::class),
            ['default_sort_by', 'is_anchor']
        );

        self::assertFalse($policy->isMappable('default_sort_by'));
        self::assertFalse($policy->isMappable('is_anchor'));
        self::assertFalse($policy->isMappable('include_in_menu'));
        self::assertFalse($policy->isMappable('name'));
        self::assertTrue($policy->isMappable('is_active'));
        self::assertTrue($policy->isMappable('custom_category_badge'));
        self::assertFalse($policy->isRequiredMapping('include_in_menu', true));
        self::assertFalse($policy->isRequiredMapping('name', true));
        self::assertTrue($policy->isRequiredMapping('is_active', false));
        self::assertFalse($policy->isRequiredMapping('custom_category_badge', false));
        self::assertSame(['include_in_menu' => 0], $policy->getManualCreationValues());
        self::assertSame(['is_active'], $policy->getMappedCreationAttributeCodes());
    }

    public function testUnknownModeFallsBackToRequiredMapping(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('unsupported');
        $policy = new CategoryAttributePolicy(
            $scopeConfig,
            $this->createStub(CategoryNameTargetProviderInterface::class)
        );

        self::assertTrue($policy->isMappable('include_in_menu'));
        self::assertTrue($policy->isMappable('name'));
        self::assertTrue($policy->isRequiredMapping('include_in_menu', false));
        self::assertTrue($policy->isRequiredMapping('name', false));
        self::assertSame([], $policy->getManualCreationValues());
        self::assertSame(['include_in_menu', 'is_active'], $policy->getMappedCreationAttributeCodes());
    }
}
