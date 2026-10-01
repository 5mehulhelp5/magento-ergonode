<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductAttribute\Model\ProductAttributePlacementPolicy;
use Ergonode\AttributeConsumer\Api\ErgonodeAttributeProviderInterface;
use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeAutoMapper;
use Ergonode\ProductAttributeConsumer\Model\Mapping\IdenticalCodeAttributeCreator;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingSaver;
use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use Ergonode\ProductAttribute\Model\Mapping\AutomaticAttributeMappingPolicy;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSuggester;
use Ergonode\ProductAttribute\Model\Mapping\ProductAttributeMappingCompatibility;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoAttributeCreator;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class AttributeAutoMapperTest extends TestCase
{
    public function testSuggestUsesCodesTypesVisibilityAndExistingMappings(): void
    {
        $ergonodeProvider = $this->createStub(ErgonodeAttributeProviderInterface::class);
        $ergonodeProvider->method('getAttributeMap')->willReturn(
            $this->attributes(
                [
                'color' => 'select',
                'hidden' => 'text',
                'name' => 'textarea',
                'size' => 'text',
                'used' => 'text',
                'weight' => 'unit',
                ]
            )
        );
        $magentoProvider = $this->createStub(MagentoAttributeProvider::class);
        $magentoProvider->method('getAttributeMap')->willReturn(
            $this->attributes(
                [
                'color' => 'select',
                'hidden' => 'text',
                'name' => 'text',
                'size' => 'decimal',
                'used' => 'text',
                'weight' => 'decimal',
                ]
            )
        );
        $mapper = new AttributeAutoMapper(
            $ergonodeProvider,
            $magentoProvider,
            $this->createStub(AttributeMappingProvider::class),
            new AttributeMappingSuggester(
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class))
            ),
            $this->createStub(AttributeMappingSaver::class),
            new IdenticalCodeAttributeCreator(
                $ergonodeProvider,
                $magentoProvider,
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class)),
                $this->createStub(MagentoAttributeCreator::class)
            ),
            $this->createStub(ProductAttributeConfigProvider::class)
        );

        $result = $mapper->suggest(
            [
                ['left' => $this->attribute('color', 'select'), 'right' => null],
                [
                    'left' => $this->attribute('used', 'text'),
                    'right' => $this->attribute('used', 'text'),
                ],
            ],
            [['source' => 'ergo', 'code' => 'hidden', 'active' => false]]
        );

        self::assertSame(['color', 'weight'], array_column(array_column($result['matches'], 'left'), 'code'));
        self::assertSame(
            [
            [
                'code' => 'name',
                'ergonode_type' => 'textarea',
                'magento_type' => 'text',
                'reason' => 'incompatible_types',
            ],
            [
                'code' => 'size',
                'ergonode_type' => 'text',
                'magento_type' => 'decimal',
                'reason' => 'incompatible_types',
            ],
            ],
            $result['conflicts']
        );
        self::assertSame('ergo', $result['matches'][0]['left']['source']);
        self::assertSame('magento', $result['matches'][0]['right']['source']);
    }

    public function testSuggestExcludesSystemAttributesFromAutomaticMatches(): void
    {
        $ergonodeProvider = $this->createStub(ErgonodeAttributeProviderInterface::class);
        $ergonodeProvider->method('getAttributeMap')->willReturn(
            $this->attributes(
                [
                'color' => 'select',
                'qty' => 'text',
                'status' => 'select',
                ]
            )
        );
        $magentoProvider = $this->createStub(MagentoAttributeProvider::class);
        $magentoProvider->method('getAttributeMap')->willReturn(
            $this->attributes(
                [
                'color' => 'select',
                'qty' => 'text',
                'status' => 'select',
                ]
            )
        );

        $result = (new AttributeAutoMapper(
            $ergonodeProvider,
            $magentoProvider,
            $this->createStub(AttributeMappingProvider::class),
            new AttributeMappingSuggester(
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class))
            ),
            $this->createStub(AttributeMappingSaver::class),
            new IdenticalCodeAttributeCreator(
                $ergonodeProvider,
                $magentoProvider,
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class)),
                $this->createStub(MagentoAttributeCreator::class)
            ),
            $this->createStub(ProductAttributeConfigProvider::class)
        ))->suggest([]);

        self::assertSame(['color'], array_column(array_column($result['matches'], 'left'), 'code'));
        self::assertSame([], $result['conflicts']);
    }

    public function testSynchronizePersistsOnlySuggestedMappings(): void
    {
        $ergonodeProvider = $this->createStub(ErgonodeAttributeProviderInterface::class);
        $ergonodeProvider->method('getAttributeMap')->willReturn(
            $this->attributes(
                [
                'color' => 'select',
                'status' => 'select',
                ]
            )
        );
        $magentoProvider = $this->createStub(MagentoAttributeProvider::class);
        $magentoProvider->method('getAttributeMap')->willReturn(
            $this->attributes(
                [
                'color' => 'select',
                'status' => 'select',
                ]
            )
        );
        $mappingProvider = $this->createMock(AttributeMappingProvider::class);
        $mappingProvider->expects(self::once())->method('getMappings')->willReturn(
            [[
            'left' => $this->attribute('status', 'select'),
            'right' => $this->attribute('status', 'select'),
            ]]
        );
        $mappingProvider->expects(self::once())->method('clearCache');
        $saver = $this->createMock(AttributeMappingSaver::class);
        $saver->expects(self::once())
            ->method('saveAdditions')
            ->with(
                self::callback(
                    static function (array $mappings): bool {
                        return count($mappings) === 1
                        && $mappings[0]['left']['code'] === 'color'
                        && $mappings[0]['right']['code'] === 'color';
                    }
                )
            )
            ->willReturn(['inserted' => 1, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0]);
        $config = $this->createStub(ProductAttributeConfigProvider::class);
        $config->method('shouldMapIdenticalCodes')->willReturn(true);

        $result = (new AttributeAutoMapper(
            $ergonodeProvider,
            $magentoProvider,
            $mappingProvider,
            new AttributeMappingSuggester(
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class))
            ),
            $saver,
            new IdenticalCodeAttributeCreator(
                $ergonodeProvider,
                $magentoProvider,
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class)),
                $this->createStub(MagentoAttributeCreator::class)
            ),
            $config
        ))->synchronize();

        self::assertSame(
            [
            'matched' => 1,
            'conflicts' => 0,
            'created' => 0,
            'inserted' => 1,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            ],
            $result
        );
    }

    public function testSynchronizationDoesNotCreateMissingSystemAttribute(): void
    {
        $ergonodeProvider = $this->createStub(ErgonodeAttributeProviderInterface::class);
        $ergonodeProvider->method('getAttributeMap')->willReturn($this->attributes(['qty' => 'text']));
        $magentoProvider = $this->createStub(MagentoAttributeProvider::class);
        $magentoProvider->method('getAttributeMap')->willReturn([]);
        $creator = $this->createMock(MagentoAttributeCreator::class);
        $creator->expects(self::never())->method('previewFromErgonodeAttribute');
        $creator->expects(self::never())->method('createFromErgonodeAttribute');
        $mappingProvider = $this->createStub(AttributeMappingProvider::class);
        $mappingProvider->method('getMappings')->willReturn([]);
        $saver = $this->createMock(AttributeMappingSaver::class);
        $saver->expects(self::once())
            ->method('saveAdditions')
            ->with([])
            ->willReturn(['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0]);
        $config = $this->createStub(ProductAttributeConfigProvider::class);
        $config->method('shouldMapIdenticalCodes')->willReturn(true);

        $result = (new AttributeAutoMapper(
            $ergonodeProvider,
            $magentoProvider,
            $mappingProvider,
            new AttributeMappingSuggester(
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class))
            ),
            $saver,
            new IdenticalCodeAttributeCreator(
                $ergonodeProvider,
                $magentoProvider,
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class)),
                $creator
            ),
            $config
        ))->synchronize();

        self::assertSame(
            [
            'matched' => 0,
            'conflicts' => 0,
            'created' => 0,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            ],
            $result
        );
    }

    public function testSynchronizationIsDisabledWithoutIdenticalCodeConfiguration(): void
    {
        $ergonodeProvider = $this->createMock(ErgonodeAttributeProviderInterface::class);
        $ergonodeProvider->expects(self::never())->method('getAttributeMap');
        $mappingProvider = $this->createMock(AttributeMappingProvider::class);
        $mappingProvider->expects(self::never())->method('getMappings');
        $saver = $this->createMock(AttributeMappingSaver::class);
        $saver->expects(self::never())->method('saveAdditions');

        $result = (new AttributeAutoMapper(
            $ergonodeProvider,
            $this->createStub(MagentoAttributeProvider::class),
            $mappingProvider,
            new AttributeMappingSuggester(
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class))
            ),
            $saver,
            new IdenticalCodeAttributeCreator(
                $ergonodeProvider,
                $this->createStub(MagentoAttributeProvider::class),
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class)),
                $this->createStub(MagentoAttributeCreator::class)
            ),
            $this->createStub(ProductAttributeConfigProvider::class)
        ))->synchronize();

        self::assertSame(
            [
            'matched' => 0,
            'conflicts' => 0,
            'created' => 0,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            ],
            $result
        );
    }

    public function testSynchronizationCreatesAndMapsMissingAttributeWithIdenticalCode(): void
    {
        $left = $this->attribute('color', 'select');
        $mappedLeft = $left + ['source' => 'ergo'];
        $right = $this->attribute('color', 'select') + [
            'source' => 'magento',
            'created' => true,
        ];
        $ergonodeProvider = $this->createStub(ErgonodeAttributeProviderInterface::class);
        $ergonodeProvider->method('getAttributeMap')->willReturn(['color' => $left]);
        $magentoProvider = $this->createMock(MagentoAttributeProvider::class);
        $magentoProvider->method('getAttributeMap')->willReturn([]);
        $magentoProvider->expects(self::once())->method('clearCache');
        $creator = $this->createMock(MagentoAttributeCreator::class);
        $creator->expects(self::once())
            ->method('previewFromErgonodeAttribute')
            ->with($mappedLeft)
            ->willReturn($right);
        $creator->expects(self::once())
            ->method('createFromErgonodeAttribute')
            ->with($mappedLeft)
            ->willReturn($right);
        $mappingProvider = $this->createStub(AttributeMappingProvider::class);
        $mappingProvider->method('getMappings')->willReturn([]);
        $saver = $this->createMock(AttributeMappingSaver::class);
        $saver->expects(self::once())
            ->method('saveAdditions')
            ->with([['left' => $mappedLeft, 'right' => $right]])
            ->willReturn(['inserted' => 1, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0]);
        $config = $this->createStub(ProductAttributeConfigProvider::class);
        $config->method('shouldMapIdenticalCodes')->willReturn(true);

        $result = (new AttributeAutoMapper(
            $ergonodeProvider,
            $magentoProvider,
            $mappingProvider,
            new AttributeMappingSuggester(
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class))
            ),
            $saver,
            new IdenticalCodeAttributeCreator(
                $ergonodeProvider,
                $magentoProvider,
                $this->mappingCompatibility(),
                new AutomaticAttributeMappingPolicy($this->createStub(ProductAttributePolicy::class)),
                $creator
            ),
            $config
        ))->synchronize();

        self::assertSame(1, $result['created']);
        self::assertSame(1, $result['inserted']);
    }

    /**
     * @param  array<string, string> $types
     * @return array<string, array<string, mixed>>
     */
    private function attributes(array $types): array
    {
        $attributes = [];

        foreach ($types as $code => $type) {
            $attributes[$code] = $this->attribute($code, $type);
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function attribute(string $code, string $type): array
    {
        return [
            'label' => ucfirst($code),
            'code' => $code,
            'scope' => 'global',
            'type' => $type,
            'active' => true,
        ];
    }

    private function mappingCompatibility(): ProductAttributeMappingCompatibility
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);

        return new ProductAttributeMappingCompatibility(
            new AttributeTypeCompatibility(),
            new ProductAttributePolicy(
                $scopeConfig,
                new ProductAttributePlacementPolicy(),
                $this->createStub(ProductIdentityModeProviderInterface::class),
                $this->createStub(MagentoIdentityAttributeInterface::class)
            )
        );
    }
}
