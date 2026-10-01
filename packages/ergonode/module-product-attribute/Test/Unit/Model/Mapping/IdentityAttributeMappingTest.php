<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingNormalizer;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSuggester;
use Ergonode\ProductAttribute\Model\Mapping\AutomaticAttributeMappingPolicy;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttribute\Model\Mapping\ProductAttributeMappingCompatibility;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use Ergonode\ProductAttribute\Model\ProductAttributePlacementPolicy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentityAttributeMappingTest extends TestCase
{
    #[DataProvider('modeProvider')]
    public function testOnlyConfiguredMappedIdentityIsReserved(string $mode, string $configured, bool $reserved): void
    {
        $policy = $this->policy($mode, $configured);
        self::assertSame($reserved, $policy->isIdentityAttribute(' navireo_id '));
        self::assertSame(!$reserved, $policy->isMappable('navireo_id'));
        self::assertTrue($policy->isMappable('manufacturer'));
        self::assertFalse($policy->isIdentityAttribute(''));
        self::assertSame(!$reserved, (new AutomaticAttributeMappingPolicy($policy))->isAllowed('navireo_id'));
        if ($mode === 'assigned') {
            self::assertTrue($policy->isMappable('sku'));
        }
    }

    /** @return array<string, array{string, string, bool}> */
    public static function modeProvider(): array
    {
        return [
            'mapped' => ['mapped', 'navireo_id', true],
            'assigned' => ['assigned', 'navireo_id', false],
            'historical shared' => ['shared', 'navireo_id', false],
            'unconfigured' => ['', 'navireo_id', false],
            'different attribute' => ['mapped', 'external_id', false],
            'empty attribute' => ['mapped', '', false],
        ];
    }

    public function testReservedMappingIsHiddenAndPreservedWhenSavingOtherMappings(): void
    {
        $policy = $this->policy('mapped', 'external_id');
        $normalizer = $this->normalizer($policy);
        $row = [
            'mapping_id' => 10,
            'ergonode_attribute_code' => 'remote_identity',
            'magento_attribute_code' => 'external_id',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'status' => 'complete',
            'sort_order' => 8,
            'content_hash' => 'preserved',
        ];
        $key = 'full:remote_identity|external_id';
        self::assertSame([$key => $row], $normalizer->normalize([], [$key => $row]));
        $normalized = $normalizer->normalize([
            ['left' => ['code' => 'brand', 'type' => 'text'], 'right' => ['code' => 'brand', 'type' => 'text']],
        ], [$key => $row]);
        self::assertSame($row, $normalized[$key]);
        self::assertCount(2, $normalized);
        $state = new MappingStateBuilder(new AttributeTypeCompatibility(), $policy, new ValueAdapterRegistry());
        self::assertSame([], $state->attributes([$row], [], []));
        self::assertNull($state->context($row, null, null));
    }

    public function testSavedReservationPreventsReusingItsRemoteSide(): void
    {
        $normalizer = $this->normalizer($this->policy('mapped', 'external_id'));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode attribute "remote_identity" is mapped more than once.');
        $normalizer->normalize([[
            'left' => ['code' => 'remote_identity', 'type' => 'text'],
            'right' => ['code' => 'brand', 'type' => 'text'],
        ]], ['full:remote_identity|external_id' => [
            'ergonode_attribute_code' => 'remote_identity',
            'magento_attribute_code' => 'external_id',
        ]]);
    }

    public function testManualSaveRejectsReservedAttribute(): void
    {
        $normalizer = $this->normalizer($this->policy('mapped', 'external_id'));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Magento attribute "external_id" is not available for mapping.');
        $normalizer->normalize([[
            'left' => ['code' => 'remote_identity', 'type' => 'text'],
            'right' => ['code' => 'external_id', 'type' => 'text'],
        ]]);
    }

    public function testAutomaticSuggestionSkipsIdentityAndStillMatchesOtherAttributes(): void
    {
        $policy = $this->policy('mapped', 'external_id');
        $suggester = new AttributeMappingSuggester(
            new ProductAttributeMappingCompatibility(new AttributeTypeCompatibility(), $policy),
            new AutomaticAttributeMappingPolicy($policy)
        );
        $metadata = [];
        foreach (['external_id', 'brand'] as $code) {
            $metadata[$code] = ['code' => $code, 'type' => 'text', 'active' => true];
        }
        $result = $suggester->suggest($metadata, $metadata, []);
        self::assertSame(['brand'], array_column(array_column($result['matches'], 'right'), 'code'));
        self::assertSame([], $result['conflicts']);
    }

    private function policy(string $mode, string $code): ProductAttributePolicy
    {
        $identityMode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $identityMode->method('getMode')->willReturn($mode);
        $identityAttribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $identityAttribute->method('getCode')->willReturn($code);

        return new ProductAttributePolicy(
            $this->createStub(ScopeConfigInterface::class),
            new ProductAttributePlacementPolicy(),
            $identityMode,
            $identityAttribute
        );
    }

    private function normalizer(ProductAttributePolicy $policy): AttributeMappingNormalizer
    {
        return new AttributeMappingNormalizer(
            new Json(),
            new ProductAttributeMappingCompatibility(new AttributeTypeCompatibility(), $policy),
            $policy,
            new ValueAdapterRegistry()
        );
    }
}
