<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Test\Unit\Model;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttributeHistory\Api\SourceSnapshotProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterfaceFactory;
use Ergonode\CategoryAttributeHistory\Model\SnapshotProvider;
use PHPUnit\Framework\TestCase;

class SnapshotProviderTest extends TestCase
{
    public function testNeutralRowsKeepDraftsMissingMetadataVisibilityAndDeterministicOrder(): void
    {
        $source = $this->createStub(SourceSnapshotProviderInterface::class);
        $source->method('getAttributeMap')->willReturn([
            'z_unmapped' => $this->attribute('z_unmapped'),
            'mapped' => $this->attribute('mapped') + ['active' => false],
            'draft' => $this->attribute('draft'),
        ]);
        $target = $this->createStub(MagentoAttributeProviderInterface::class);
        $target->method('getAttributeMap')->willReturn([
            'target' => $this->attribute('target'),
            'a_unmapped' => $this->attribute('a_unmapped'),
        ]);
        $targetFactory = $this->createMock(MagentoAttributeProviderInterfaceFactory::class);
        $targetFactory->expects(self::once())->method('create')->willReturn($target);
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRows')->willReturn([
            ['mapping_id' => 1, 'ergonode_attribute_code' => 'mapped', 'magento_attribute_code' => 'target'],
            ['mapping_id' => 2, 'ergonode_attribute_code' => 'missing_source',
                'magento_attribute_code' => 'missing_target'],
            ['mapping_id' => 3, 'ergonode_attribute_code' => 'draft', 'magento_attribute_code' => null],
            ['mapping_id' => 4, 'ergonode_attribute_code' => null, 'magento_attribute_code' => 'a_unmapped'],
        ]);
        $compatibility = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $compatibility->method('canMapAttributes')->willReturn(true);
        $provider = new SnapshotProvider(
            $source,
            $targetFactory,
            $reader,
            new MappingStateBuilder($compatibility)
        );

        $state = $provider->getState();

        self::assertSame(['draft', 'mapped', 'missing_source', 'z_unmapped'], array_column($state['source'], 'code'));
        self::assertSame(['a_unmapped', 'missing_target', 'target'], array_column($state['target'], 'code'));
        $sources = array_column($state['source'], null, 'code');
        $targets = array_column($state['target'], null, 'code');
        self::assertSame('target', $sources['mapped']['mapped_code']);
        self::assertFalse($sources['mapped']['active']);
        self::assertSame('mapped', $targets['target']['mapped_code']);
        self::assertNull($sources['draft']['mapped_code']);
        self::assertTrue($sources['draft']['is_draft']);
        self::assertTrue($targets['a_unmapped']['is_draft']);
        self::assertFalse($sources['z_unmapped']['is_draft']);
        self::assertFalse($sources['mapped']['is_draft']);
        self::assertFalse($targets['target']['is_draft']);
        self::assertNull($sources['z_unmapped']['mapped_code']);
        self::assertSame('missing', $sources['missing_source']['scope']);
        self::assertSame('missing_target', $sources['missing_source']['mapped_code']);
        self::assertSame('missing_source', $targets['missing_target']['mapped_code']);
    }

    /** @return array{code: string, label: string, type: string, scope: string} */
    private function attribute(string $code): array
    {
        return ['code' => $code, 'label' => $code, 'type' => 'text', 'scope' => 'global'];
    }
}
