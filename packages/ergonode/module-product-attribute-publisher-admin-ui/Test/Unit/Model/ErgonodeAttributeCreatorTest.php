<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Model;

use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\AttributeTypeResolverInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\AttributePublisherAdminUi\Model\AttributeMappingPayloadBuilder;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeAttributeCreator;
use Ergonode\ProductAttributePublisherAdminUi\Model\PublishedMetadataVerifier;
use Ergonode\AttributePublisherAdminUi\Model\ExistingSynchronizationNoticeResolver;
use Ergonode\ProductAttributePublisher\Api\AttributeSourceStateBuilderInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Ergonode\ProductAttributePublisher\Model\Publication\AttributeDefinitionPublisher;
use Ergonode\AttributePublisher\Api\AttributeCreateBatchSynchronizerInterface;

class ErgonodeAttributeCreatorTest extends TestCase
{
    #[DataProvider('optionAttributeTypes')]
    public function testPublishesOptionsWithoutSavedMappingBeforeSynchronization(
        string $magentoType,
        string $ergonodeType
    ): void {
        $state = $this->createStub(AttributeStateInterface::class);
        $state->method('getCode')->willReturn('color');
        $state->method('getOptions')->willReturn([$this->createStub(AttributeOptionStateInterface::class)]);
        $stateBuilder = $this->createMock(AttributeSourceStateBuilderInterface::class);
        $stateBuilder->expects(self::once())
            ->method('build')
            ->with('color', 'color', $ergonodeType, [85, 86, 87, 88, 89, 90])
            ->willReturn($state);
        $options = $this->createMock(MagentoOptionProvider::class);
        $options->expects(self::once())->method('getOptions')->with('color')->willReturn([
            ['code' => 'option_85', 'label' => 'Cardio'],
            ['code' => 'option_86', 'label' => 'Electronic'],
            ['code' => 'option_87', 'label' => 'Exercise'],
            ['code' => 'option_88', 'label' => 'Fashion'],
            ['code' => 'option_89', 'label' => 'Hydration'],
            ['code' => 'option_90', 'label' => 'Timepiece'],
        ]);
        $result = $this->createStub(AttributeSynchronizationResultInterface::class);
        $result->method('isSuccessful')->willReturn(true);
        $attributeSynchronizer = $this->createMock(AttributeSynchronizerInterface::class);
        $attributeSynchronizer->expects(self::once())
            ->method('synchronize')
            ->with($state, AttributeSynchronizerInterface::MODE_CREATE_ONLY)
            ->willReturn($result);
        $metadataVerifier = $this->createMock(PublishedMetadataVerifier::class);
        $metadataVerifier->expects(self::once())->method('verifyAttributes')->with(['color']);
        $rateLimitGuard = $this->createMock(SynchronizationRateLimitGuard::class);
        $rateLimitGuard->expects(self::once())->method('throwIfLimited')->with($result);
        $attributeTypeResolver = $this->createMock(AttributeTypeResolverInterface::class);
        $attributeTypeResolver->expects(self::once())
            ->method('resolve')
            ->with($magentoType)
            ->willReturn($ergonodeType);

        $warning = (new ErgonodeAttributeCreator(
            new AttributeDefinitionPublisher(
                $attributeSynchronizer,
                $this->createStub(AttributeCreateBatchSynchronizerInterface::class),
                $stateBuilder,
                $options,
                $attributeTypeResolver,
                $rateLimitGuard
            ),
            $this->createStub(AttributeMappingPayloadBuilder::class),
            $metadataVerifier,
            new ExistingSynchronizationNoticeResolver()
        ))->synchronizeFromMagento(
            [
            'code' => 'color',
            'label' => 'Color',
            'type' => $magentoType,
            'target_type' => $magentoType,
            ]
        );

        self::assertNull($warning);
    }

    /** @return array<string, array{string, string}> */
    public static function optionAttributeTypes(): array
    {
        return [
            'select' => ['select', 'select'],
            'multiselect' => ['multiselect', 'multi_select'],
        ];
    }

    public function testReturnsWarningWhenExistingAttributeIsLinked(): void
    {
        $state = $this->createStub(AttributeStateInterface::class);
        $state->method('getCode')->willReturn('sku');
        $state->method('getOptions')->willReturn([]);
        $stateBuilder = $this->createStub(AttributeSourceStateBuilderInterface::class);
        $stateBuilder->method('build')->willReturn($state);
        $result = $this->createStub(AttributeSynchronizationResultInterface::class);
        $result->method('isSuccessful')->willReturn(true);
        $result->method('getStatus')->willReturn(AttributeSynchronizationResultInterface::STATUS_NOOP);
        $synchronizer = $this->createStub(AttributeSynchronizerInterface::class);
        $synchronizer->method('synchronize')->willReturn($result);
        $typeResolver = $this->createStub(AttributeTypeResolverInterface::class);
        $typeResolver->method('resolve')->willReturn('text');
        $metadataVerifier = $this->createMock(PublishedMetadataVerifier::class);
        $metadataVerifier->expects(self::once())->method('verifyAttributes')->with(['sku']);

        $warning = (new ErgonodeAttributeCreator(
            new AttributeDefinitionPublisher(
                $synchronizer,
                $this->createStub(AttributeCreateBatchSynchronizerInterface::class),
                $stateBuilder,
                $this->createStub(MagentoOptionProvider::class),
                $typeResolver,
                $this->createStub(SynchronizationRateLimitGuard::class)
            ),
            $this->createStub(AttributeMappingPayloadBuilder::class),
            $metadataVerifier,
            new ExistingSynchronizationNoticeResolver()
        ))->synchronizeFromMagento(
            [
            'code' => 'sku',
            'label' => 'SKU',
            'type' => 'text',
            'target_type' => 'text',
            ]
        );

        self::assertSame(
            'Attribute "sku" already exists in Ergonode and has been linked to Magento attribute "sku".',
            $warning
        );
    }

    public function testBuildsBooleanOptionCodesFromMagentoAdminLabels(): void
    {
        $state = $this->createStub(AttributeStateInterface::class);
        $stateBuilder = $this->createMock(AttributeSourceStateBuilderInterface::class);
        $stateBuilder->expects(self::once())
            ->method('build')
            ->with('enabled', 'enabled', 'select', [0, 1])
            ->willReturn($state);
        $options = $this->createMock(MagentoOptionProvider::class);
        $options->expects(self::once())
            ->method('getOptions')
            ->with('enabled')
            ->willReturn(
                [
                ['code' => 'option_0', 'label' => 'No'],
                ['code' => 'option_1', 'label' => 'Yes'],
                ]
            );
        $typeResolver = $this->createStub(AttributeTypeResolverInterface::class);
        $typeResolver->method('resolve')->willReturn('select');
        $creator = new ErgonodeAttributeCreator(
            new AttributeDefinitionPublisher(
                $this->createStub(AttributeSynchronizerInterface::class),
                $this->createStub(AttributeCreateBatchSynchronizerInterface::class),
                $stateBuilder,
                $options,
                $typeResolver,
                $this->createStub(SynchronizationRateLimitGuard::class)
            ),
            $this->createStub(AttributeMappingPayloadBuilder::class),
            $this->createStub(PublishedMetadataVerifier::class),
            new ExistingSynchronizationNoticeResolver()
        );

        self::assertSame(
            $state,
            $creator->prepareState(
                [
                'code' => 'enabled',
                'type' => 'boolean',
                'target_type' => 'select',
                ]
            )
        );
    }
}
