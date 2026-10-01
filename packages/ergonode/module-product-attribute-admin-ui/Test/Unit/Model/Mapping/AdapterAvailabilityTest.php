<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Unit\Model\Mapping;

use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use PHPUnit\Framework\TestCase;

class AdapterAvailabilityTest extends TestCase
{
    public function testRecordAndSaveResponseExposeUnavailableDirection(): void
    {
        $state = $this->createStub(MappingStateBuilder::class);
        $state->method('attributes')->willReturn([[
            'mapping_id' => 1,
            'left' => ['code' => 'category', 'label' => 'Category', 'type' => 'text', 'scope' => 'global'],
            'right' => ['code' => 'reference', 'label' => 'Reference', 'type' => 'text', 'scope' => 'global'],
            'value_adapter' => 'category_reference',
            'adapter_availability' => ['import' => false, 'publish' => true],
        ]]);
        $provider = new AttributeMappingProvider(
            $this->createStub(MappingReaderInterface::class),
            $this->createStub(ErgonodeMetadataProviderInterface::class),
            $this->createStub(MagentoAttributeProvider::class),
            $state,
            $this->createStub(ErgonodeMetadataProviderInterface::class)
        );
        $mapping = $provider->getMappings()[0];
        self::assertSame('error', $mapping['validation_tone']);
        self::assertStringContainsString('category_reference', $mapping['validation_message']);
        self::assertStringContainsString('Import: unavailable', $mapping['validation_message']);
        self::assertStringContainsString('Publication: available', $mapping['validation_message']);
        self::assertSame([
            'reference' => ['message' => $mapping['validation_message'], 'tone' => 'error'],
        ], $provider->getValidationMessages());
    }
}
