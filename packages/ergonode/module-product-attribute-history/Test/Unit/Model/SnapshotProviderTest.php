<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Model;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProviderFactory;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProviderFactory;
use Ergonode\ProductAttributeHistory\Model\SnapshotProvider;
use Ergonode\ProductAttributeHistory\Model\OptionSnapshotProvider;
use PHPUnit\Framework\TestCase;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;

class SnapshotProviderTest extends TestCase
{
    public function testNeutralRowsKeepDraftsMissingMetadataVisibilityAndDeterministicOrder(): void
    {
        $source = $this->createStub(ErgonodeAttributeProvider::class);
        $source->method('getAttributeMap')->willReturn([
            'z_unmapped' => $this->attribute('z_unmapped'),
            'mapped' => $this->attribute('mapped') + ['active' => false],
            'draft' => $this->attribute('draft'),
        ]);
        $target = $this->createStub(MagentoAttributeProvider::class);
        $target->method('getAttributeMap')->willReturn([
            'target' => $this->attribute('target'),
            'a_unmapped' => $this->attribute('a_unmapped'),
        ]);
        $sourceFactory = $this->createMock(ErgonodeAttributeProviderFactory::class);
        $sourceFactory->expects(self::once())->method('create')->willReturn($source);
        $targetFactory = $this->createMock(MagentoAttributeProviderFactory::class);
        $targetFactory->expects(self::once())->method('create')->willReturn($target);
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRows')->willReturn([
            ['mapping_id' => 1, 'ergonode_attribute_code' => 'mapped', 'magento_attribute_code' => 'target'],
            ['mapping_id' => 2, 'ergonode_attribute_code' => 'missing_source',
                'magento_attribute_code' => 'missing_target'],
            ['mapping_id' => 3, 'ergonode_attribute_code' => 'draft', 'magento_attribute_code' => null],
        ]);
        $compatibility = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $compatibility->method('canMapAttributes')->willReturn(true);
        $policy = $this->createStub(ProductAttributePolicy::class);
        $policy->method('isMappable')->willReturn(true);
        $policy->method('isErgonodeMappable')->willReturn(true);
        $provider = new SnapshotProvider(
            $sourceFactory,
            $targetFactory,
            $reader,
            new MappingStateBuilder($compatibility, $policy, new ValueAdapterRegistry()),
            $this->createStub(OptionSnapshotProvider::class)
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
        self::assertNull($sources['z_unmapped']['mapped_code']);
        self::assertSame('missing', $sources['missing_source']['type']);
        self::assertSame('missing_target', $sources['missing_source']['mapped_code']);
        self::assertSame('missing_source', $targets['missing_target']['mapped_code']);
    }

    /** @return array{code: string, label: string, type: string, scope: string} */
    private function attribute(string $code): array
    {
        return ['code' => $code, 'label' => $code, 'type' => 'text', 'scope' => 'global'];
    }
}
