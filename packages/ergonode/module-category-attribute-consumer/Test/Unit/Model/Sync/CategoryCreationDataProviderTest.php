<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;

use Ergonode\CategoryAttributeConsumer\Model\Import\CategoryEntityLoader;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeValueMapper;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryCreationConfigurationProvider;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryCreationDataProvider;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryDataWorkProvider;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryCreationDataProviderTest extends TestCase
{
    private const array ENTITY = [
        'code' => 'chairs',
        'labels' => ['en_US' => 'Chairs'],
        'attributes' => [
            ['code' => 'active', 'type' => 'boolean', 'values' => ['en_US' => true]],
            ['code' => 'menu', 'type' => 'boolean', 'values' => ['en_US' => false]],
        ],
        'hash' => 'hash',
        'raw' => [],
    ];

    public function testUsesFixedValuesWithoutFetchingCategoryEntityWhenAttributeProcessIsDisabled(): void
    {
        $configuration = $this->createStub(CategoryCreationConfigurationProvider::class);
        $configuration->method('get')->willReturn([
            'attributes_enabled' => false,
            'fixed_values' => ['is_active' => 1, 'include_in_menu' => 0],
            'mapped_attribute_codes' => [],
        ]);
        $loader = $this->createMock(CategoryEntityLoader::class);
        $loader->expects(self::never())->method('load');

        $result = (new CategoryCreationDataProvider(
            $this->createMock(CategoryAttributeSourcePreparation::class),
            $configuration,
            $loader,
            $this->createStub(CategoryAttributeValueMapper::class),
            $this->workProvider(true)
        ))->get('chairs');

        self::assertSame(['is_active' => 1, 'include_in_menu' => 0], $result['values']);
        self::assertNull($result['entity']);
    }

    public function testFetchesOnlyNewCodeAndResolvesRequiredMappedCreationValues(): void
    {
        $configuration = $this->createStub(CategoryCreationConfigurationProvider::class);
        $configuration->method('get')->willReturn([
            'attributes_enabled' => true,
            'fixed_values' => [],
            'mapped_attribute_codes' => ['is_active', 'include_in_menu'],
        ]);
        $loader = $this->createMock(CategoryEntityLoader::class);
        $loader->expects(self::once())->method('load')->with('chairs')->willReturn(self::ENTITY);
        $mapper = $this->createMock(CategoryAttributeValueMapper::class);
        $mapper->expects(self::once())->method('map')
            ->with(self::ENTITY['attributes'], ['is_active', 'include_in_menu'])
            ->willReturn(['is_active' => [0 => 1], 'include_in_menu' => [0 => 0]]);

        $result = (new CategoryCreationDataProvider(
            $this->createMock(CategoryAttributeSourcePreparation::class),
            $configuration,
            $loader,
            $mapper,
            $this->workProvider(false)
        ))
            ->get('chairs');

        self::assertSame(['is_active' => 1, 'include_in_menu' => 0], $result['values']);
        self::assertSame(self::ENTITY, $result['entity']);
    }

    public function testRejectsMissingRequiredMappedAdminValue(): void
    {
        $configuration = $this->createStub(CategoryCreationConfigurationProvider::class);
        $configuration->method('get')->willReturn([
            'attributes_enabled' => true,
            'fixed_values' => ['include_in_menu' => 1],
            'mapped_attribute_codes' => ['is_active'],
        ]);
        $loader = $this->createStub(CategoryEntityLoader::class);
        $loader->method('load')->willReturn(self::ENTITY);
        $mapper = $this->createStub(CategoryAttributeValueMapper::class);
        $mapper->method('map')->willReturn([]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('no mapped admin value');

        (new CategoryCreationDataProvider(
            $this->createMock(CategoryAttributeSourcePreparation::class),
            $configuration,
            $loader,
            $mapper,
            $this->workProvider(true)
        ))
            ->get('chairs');
    }

    public function testEnabledProcessWithNoUpdatesDoesNotFetchAnyOf1871NewCategories(): void
    {
        $configuration = $this->createStub(CategoryCreationConfigurationProvider::class);
        $configuration->method('get')->willReturn([
            'attributes_enabled' => true,
            'fixed_values' => ['is_active' => 1, 'include_in_menu' => 1],
            'mapped_attribute_codes' => [],
        ]);
        $loader = $this->createMock(CategoryEntityLoader::class);
        $loader->expects(self::never())->method('load');
        $mapper = $this->createMock(CategoryAttributeValueMapper::class);
        $mapper->expects(self::never())->method('map');
        $provider = new CategoryCreationDataProvider(
            $this->createMock(CategoryAttributeSourcePreparation::class),
            $configuration,
            $loader,
            $mapper,
            $this->workProvider(false)
        );

        for ($index = 0; $index < 1871; $index++) {
            self::assertSame([
                'values' => ['is_active' => 1, 'include_in_menu' => 1],
                'entity' => null,
            ], $provider->get('category_' . $index));
        }
    }

    public function testFixedCreationValuesStillFetchEntityForConfiguredDataUpdates(): void
    {
        $configuration = $this->createStub(CategoryCreationConfigurationProvider::class);
        $configuration->method('get')->willReturn([
            'attributes_enabled' => true,
            'fixed_values' => ['is_active' => 1, 'include_in_menu' => 0],
            'mapped_attribute_codes' => [],
        ]);
        $loader = $this->createMock(CategoryEntityLoader::class);
        $loader->expects(self::once())->method('load')->with('chairs')->willReturn(self::ENTITY);
        $mapper = $this->createMock(CategoryAttributeValueMapper::class);
        $mapper->expects(self::never())->method('map');

        $result = (new CategoryCreationDataProvider(
            $this->createMock(CategoryAttributeSourcePreparation::class),
            $configuration,
            $loader,
            $mapper,
            $this->workProvider(true)
        ))
            ->get('chairs');

        self::assertSame(['is_active' => 1, 'include_in_menu' => 0], $result['values']);
        self::assertSame(self::ENTITY, $result['entity']);
    }

    private function workProvider(bool $hasWork): CategoryDataWorkProvider
    {
        $provider = $this->createStub(CategoryDataWorkProvider::class);
        $provider->method('hasWork')->willReturn($hasWork);

        return $provider;
    }
}
