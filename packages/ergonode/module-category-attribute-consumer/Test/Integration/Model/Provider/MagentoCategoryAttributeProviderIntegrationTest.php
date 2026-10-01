<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Provider;

use Ergonode\CategoryAttribute\Model\Provider\MagentoCategoryAttributeProvider;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true)]
class MagentoCategoryAttributeProviderIntegrationTest extends TestCase
{
    public function testNativeMappableAttributesHaveExpectedTypes(): void
    {
        $provider = Bootstrap::getObjectManager()->get(MagentoCategoryAttributeProvider::class);

        self::assertSame('boolean', $provider->getAttribute('is_active')['type'] ?? null);
        self::assertSame('boolean', $provider->getAttribute('include_in_menu')['type'] ?? null);
        self::assertSame('text', $provider->getAttribute('meta_title')['type'] ?? null);
        self::assertSame('textarea', $provider->getAttribute('meta_keywords')['type'] ?? null);
    }
}
