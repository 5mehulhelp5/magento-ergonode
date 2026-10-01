<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Config;

use Ergonode\TemplateAttributeConsumer\Model\Config\TemplateAttributeConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class TemplateAttributeConfigProviderTest extends TestCase
{
    public function testReadsTemplateStructureImportSettings(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => in_array($path, [
                'ergonode_templates/import/sync_attributes',
                'ergonode_templates/import/sync_sections',
            ], true)
        );
        $provider = new TemplateAttributeConfigProvider($scopeConfig);

        self::assertTrue($provider->shouldSyncAttributes());
        self::assertTrue($provider->shouldSyncSections());
    }
}
