<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Test\Unit\Model;

use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Ergonode\Template\Api\TemplateSnapshotProviderInterface;
use Ergonode\TemplateAdminUi\Model\TemplateNameResolver;
use Ergonode\TemplateAdminUi\Model\TemplateUiProvider;
use PHPUnit\Framework\TestCase;

class TemplateUiProviderTest extends TestCase
{
    public function testExposesTemplateAndAttributeSetNamesForMappedPair(): void
    {
        $snapshotProvider = $this->createMock(TemplateSnapshotProviderInterface::class);
        $snapshotProvider->expects(self::once())->method('getTemplates')
            ->willReturn([[
                'entity_id' => '7',
                'code' => 'bag',
                'attribute_set_id' => '15',
                'attribute_set_name' => 'Bag',
                'is_deleted' => '0',
                'synced_at' => '2026-08-25 12:00:00',
                'updated_at' => '2026-08-25 12:00:00',
                'raw_json' => '{"name":[{"language":"en_GB","value":"Bag"}]}',
            ]]);

        $nameResolver = $this->createMock(TemplateNameResolver::class);
        $nameResolver->expects(self::once())
            ->method('resolve')
            ->with('{"name":[{"language":"en_GB","value":"Bag"}]}', 'bag')
            ->willReturn('Bag');
        $nameResolver->expects(self::once())
            ->method('resolveAll')
            ->with('{"name":[{"language":"en_GB","value":"Bag"}]}')
            ->willReturn(['Bag', 'Torba']);
        $provider = new TemplateUiProvider(
            $snapshotProvider,
            $this->createStub(ProductAttributeSetProviderInterface::class),
            $nameResolver
        );

        $templates = $provider->getTemplates();

        self::assertSame('Bag', $templates[0]['name']);
        self::assertSame(['Bag', 'Torba'], $templates[0]['names']);
        self::assertSame('Bag', $templates[0]['attribute_set_name']);
        self::assertSame(15, $templates[0]['attribute_set_id']);
    }

    public function testExposesOrphanedAttributeSetMappingAsUnmapped(): void
    {
        $snapshotProvider = $this->createMock(TemplateSnapshotProviderInterface::class);
        $snapshotProvider->expects(self::once())->method('getTemplates')
            ->willReturn([[
                'entity_id' => '8',
                'code' => 'orphaned_bag',
                'attribute_set_id' => '65535',
                'attribute_set_name' => null,
                'is_deleted' => '0',
                'synced_at' => '2026-09-23 08:00:00',
                'updated_at' => '2026-09-23 08:00:00',
                'raw_json' => '{"name":[{"language":"en_GB","value":"Orphaned bag"}]}',
            ]]);

        $nameResolver = $this->createMock(TemplateNameResolver::class);
        $nameResolver->expects(self::once())
            ->method('resolve')
            ->willReturn('Orphaned bag');
        $nameResolver->expects(self::once())
            ->method('resolveAll')
            ->willReturn(['Orphaned bag']);
        $provider = new TemplateUiProvider(
            $snapshotProvider,
            $this->createStub(ProductAttributeSetProviderInterface::class),
            $nameResolver
        );

        $template = $provider->getTemplates()[0];

        self::assertNull($template['attribute_set_id']);
        self::assertSame('', $template['attribute_set_name']);
        self::assertSame('Brak attribute set', $template['status']);
        self::assertSame('warning', $template['status_tone']);
    }
}
