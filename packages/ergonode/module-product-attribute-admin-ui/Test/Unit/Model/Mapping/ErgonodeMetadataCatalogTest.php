<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataSourceInterface;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\ErgonodeMetadataCatalog;
use PHPUnit\Framework\TestCase;

class ErgonodeMetadataCatalogTest extends TestCase
{
    public function testSavedMappingRemainsVisibleButIsNotVerifiedWithoutAnInboundSource(): void
    {
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRows')->willReturn([[
            'mapping_id' => 7,
            'ergonode_attribute_code' => 'color',
            'ergonode_type' => 'select',
        ]]);
        $reader->method('getOptionRows')->willReturn([[
            'ergonode_option_code' => 'blue',
        ]]);
        $reader->method('getCompleteOptionCounts')->willReturn([7 => 1]);
        $catalog = new ErgonodeMetadataCatalog($reader);

        self::assertSame('color', $catalog->getAttribute('color')['code']);
        self::assertFalse($catalog->getAttribute('color')['active']);
        self::assertSame([], $catalog->getVerifiedAttributeMap());
        self::assertSame('blue', $catalog->getOptions('color')[0]['code']);
        self::assertSame([], $catalog->getVerifiedOptions('color'));

        $catalog->recordAttribute([
            'code' => 'color', 'label' => 'Color', 'type' => 'select', 'scope' => 'global', 'active' => true,
        ]);
        $catalog->recordOptions('color', [[
            'code' => 'blue', 'label' => 'Blue', 'type' => 'option', 'scope' => 'pl_PL', 'active' => true,
        ]]);

        self::assertSame('Color', $catalog->getAttribute('color')['label']);
        self::assertSame('Blue', $catalog->getOptions('color')[0]['label']);
        self::assertArrayHasKey('blue', $catalog->getVerifiedOptions('color'));

        $catalog->recordOptions('color', [[
            'code' => 'green', 'label' => 'Green', 'type' => 'option', 'scope' => 'pl_PL', 'active' => true,
        ]]);
        self::assertSame(['color' => 2], $catalog->getOptionCounts(['color']));
    }

    public function testOptionalSourceProvidesVerifiedMetadata(): void
    {
        $reader = $this->createStub(MappingReaderInterface::class);
        $source = $this->createStub(ErgonodeMetadataSourceInterface::class);
        $source->method('getAttributes')->willReturn([[
            'code' => 'size', 'label' => 'Size', 'type' => 'select', 'scope' => 'global', 'active' => true,
        ]]);
        $source->method('getOptions')->willReturn([[
            'code' => 'large', 'label' => 'Large', 'type' => 'option', 'scope' => 'pl_PL', 'active' => true,
        ]]);
        $catalog = new ErgonodeMetadataCatalog($reader, [$source]);

        self::assertArrayHasKey('size', $catalog->getVerifiedAttributeMap());
        self::assertArrayHasKey('large', $catalog->getVerifiedOptions('size'));
        self::assertSame('Large', $catalog->getOptions('size')[0]['label']);
    }

    public function testSnapshotKeepsRemoteLanguageNamesForAutoMatching(): void
    {
        $reader = $this->createStub(MappingReaderInterface::class);
        $remote = $this->createStub(ErgonodeMetadataSourceInterface::class);
        $remote->method('getOptions')->willReturn([[
            'code' => 'legacy_5', 'label' => 'English', 'names' => ['pl_PL' => 'Polski'],
            'type' => 'option', 'active' => true,
        ]]);
        $snapshot = $this->createStub(ErgonodeMetadataSourceInterface::class);
        $snapshot->method('getOptions')->willReturn([[
            'code' => 'legacy_5', 'label' => 'Snapshot', 'type' => 'option', 'active' => true,
        ]]);

        $catalog = new ErgonodeMetadataCatalog($reader, [$remote, $snapshot]);

        self::assertSame('Snapshot', $catalog->getVerifiedOptions('color')['legacy_5']['label']);
        self::assertSame(['pl_PL' => 'Polski'], $catalog->getVerifiedOptions('color')['legacy_5']['names']);
    }
}
