<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Provider;

use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoAttributeCreator;
use Ergonode\ProductAttributeConsumer\Model\Mapping\MagentoAttributeTypeRecommender;
use Ergonode\Attribute\Model\MagentoAttributeTypeResolver;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Backend\Price;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PackHauer\UnitAttribute\Api\UnitAttributeMetadataInterface;

class MagentoAttributeCreatorTest extends TestCase
{
    /**
     * @param class-string|string $expectedBackend
     */
    #[DataProvider('numericAttributeTypesProvider')]
    public function testCreatesNumericTypesWithTheirMagentoInputAndDecimalStorage(
        string $ergonodeType,
        string $expectedInput,
        string $expectedBackend
    ): void {
        $repository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('startSetup');
        $connection->expects($this->once())->method('endSetup');
        $dataSetup = $this->createStub(ModuleDataSetupInterface::class);
        $dataSetup->method('getConnection')->willReturn($connection);
        $eavSetup = $this->createMock(EavSetup::class);
        $eavSetup->expects($this->once())
            ->method('addAttribute')
            ->with(
                Product::ENTITY,
                'measurement',
                self::callback(static function (array $data) use ($expectedInput, $expectedBackend): bool {
                    self::assertSame('decimal', $data['type']);
                    self::assertSame($expectedInput, $data['input']);
                    self::assertSame($expectedBackend, $data['backend']);
                    self::assertSame('validate-number', $data['frontend_class']);

                    return true;
                })
            );
        $eavSetupFactory = $this->createStub(EavSetupFactory::class);
        $eavSetupFactory->method('create')->willReturn($eavSetup);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects($this->once())->method('clear');
        $creator = new MagentoAttributeCreator(
            $dataSetup,
            $eavSetupFactory,
            $eavConfig,
            $repository,
            $this->createStub(UnitAttributeMetadataInterface::class),
            new MagentoAttributeTypeResolver(),
            $this->createPassthroughTypeRecommender()
        );

        $creator->createFromErgonodeAttribute([
            'label' => 'Measurement',
            'code' => 'measurement',
            'scope' => 'global',
            'type' => $ergonodeType,
        ]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function numericAttributeTypesProvider(): array
    {
        return [
            'numeric' => ['numeric', 'text', ''],
            'price' => ['price', 'price', Price::class],
        ];
    }

    public function testPreviewsUnitAsTheDedicatedMagentoInputType(): void
    {
        $repository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $metadata = $this->createMock(UnitAttributeMetadataInterface::class);
        $metadata->expects($this->once())
            ->method('withUnit')
            ->with(null, 'CENTIMETER', 'cm')
            ->willReturn('{"vendivo_unit":{"name":"CENTIMETER","symbol":"cm"}}');
        $creator = new MagentoAttributeCreator(
            $this->createStub(ModuleDataSetupInterface::class),
            $this->createStub(EavSetupFactory::class),
            $this->createStub(EavConfig::class),
            $repository,
            $metadata,
            new MagentoAttributeTypeResolver(),
            $this->createPassthroughTypeRecommender()
        );

        $preview = $creator->previewFromErgonodeAttribute([
            'label' => 'Length',
            'code' => 'length',
            'scope' => 'global',
            'type' => 'unit',
            'parameters' => ['unitName' => 'CENTIMETER', 'unitSymbol' => 'cm'],
        ]);

        self::assertSame('unit', $preview['type']);
        self::assertTrue($preview['created']);
    }

    public function testRejectsUnitWithoutDefinitionParameters(): void
    {
        $repository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $creator = new MagentoAttributeCreator(
            $this->createStub(ModuleDataSetupInterface::class),
            $this->createStub(EavSetupFactory::class),
            $this->createStub(EavConfig::class),
            $repository,
            $this->createStub(UnitAttributeMetadataInterface::class),
            new MagentoAttributeTypeResolver(),
            $this->createPassthroughTypeRecommender()
        );

        $this->expectException(LocalizedException::class);
        $creator->previewFromErgonodeAttribute([
            'label' => 'Length',
            'code' => 'length',
            'scope' => 'global',
            'type' => 'unit',
        ]);
    }

    public function testPreviewsSelectWithBooleanOptionsAsMagentoBoolean(): void
    {
        $repository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $recommender = $this->createMock(MagentoAttributeTypeRecommender::class);
        $recommender->expects($this->once())
            ->method('recommend')
            ->with('is_enabled', 'select')
            ->willReturn('boolean');
        $creator = new MagentoAttributeCreator(
            $this->createStub(ModuleDataSetupInterface::class),
            $this->createStub(EavSetupFactory::class),
            $this->createStub(EavConfig::class),
            $repository,
            $this->createStub(UnitAttributeMetadataInterface::class),
            new MagentoAttributeTypeResolver(),
            $recommender
        );

        $preview = $creator->previewFromErgonodeAttribute([
            'label' => 'Enabled',
            'code' => 'is_enabled',
            'scope' => 'global',
            'type' => 'select',
        ]);

        self::assertSame('boolean', $preview['type']);
        self::assertTrue($preview['created']);
    }

    private function createPassthroughTypeRecommender(): MagentoAttributeTypeRecommender
    {
        $recommender = $this->createStub(MagentoAttributeTypeRecommender::class);
        $recommender->method('recommend')->willReturnCallback(
            static fn (string $_attributeCode, string $ergonodeType): string => $ergonodeType
        );

        return $recommender;
    }
}
