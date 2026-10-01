<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Config;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use PHPUnit\Framework\TestCase;

class CategoryAttributeConfigProviderTest extends TestCase
{
    public function testReadsIndependentCategoryAttributeConfigurationPaths(): void
    {
        $categoryConfig = $this->createStub(CategoryConfigProvider::class);
        $categoryConfig->method('isEnabled')->willReturn(true);
        $categoryConfig->method('isDataSynchronizationEnabled')->willReturn(true);

        $provider = new CategoryAttributeConfigProvider($categoryConfig);

        self::assertTrue($provider->isEnabled());
        self::assertTrue($provider->isAttributeSynchronizationEnabled());
    }
}
