<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;

use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryEntityRefresher;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumer\Model\Sync\MappedCategoryAttributeSynchronizer;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class CategoryEntityRefresherTest extends TestCase
{
    public function testRefreshesAttributesOfOnlyTheMappedMagentoCategory(): void
    {
        $context = $this->createMock(CategoryFormContextProviderInterface::class);
        $context->expects(self::once())->method('getForMagentoCategory')->with(42)->willReturn([
            'category_tree_id' => 7,
            'root_category_id' => 2,
            'ergonode_category_code' => 'chairs',
        ]);
        $synchronizer = $this->createMock(MappedCategoryAttributeSynchronizer::class);
        $synchronizer->expects(self::once())->method('synchronize')->with('chairs', 42)->willReturn([
            'snapshot' => 'updated',
            'attributes' => 2,
        ]);

        $preparation = $this->createMock(CategoryAttributeSourcePreparation::class);
        $preparation->expects(self::once())->method('prepare');
        $result = (new CategoryEntityRefresher(
            $preparation,
            $context,
            $synchronizer,
            $this->lock(),
            $this->enabledConfig(),
            $this->eligibleMappings()
        ))->refresh(42);

        self::assertSame([
            'code' => 'chairs',
            'snapshot' => 'updated',
            'attributes' => 2,
        ], $result);
    }

    public function testRejectsAnUnmappedCategoryWithoutCallingErgonode(): void
    {
        $context = $this->createStub(CategoryFormContextProviderInterface::class);
        $context->method('getForMagentoCategory')->willReturn([
            'category_tree_id' => 7,
            'root_category_id' => 2,
            'ergonode_category_code' => null,
        ]);
        $synchronizer = $this->createMock(MappedCategoryAttributeSynchronizer::class);
        $synchronizer->expects(self::never())->method('synchronize');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The category is not mapped with Ergonode.');
        $this->service($context, $synchronizer)->refresh(42);
    }

    private function service(
        CategoryFormContextProviderInterface $context,
        ?MappedCategoryAttributeSynchronizer $synchronizer = null
    ): CategoryEntityRefresher {
        $preparation = $this->createMock(CategoryAttributeSourcePreparation::class);
        $preparation->expects(self::never())->method('prepare');
        return new CategoryEntityRefresher(
            $preparation,
            $context,
            $synchronizer ?? $this->createStub(MappedCategoryAttributeSynchronizer::class),
            $this->lock(),
            $this->enabledConfig(),
            $this->eligibleMappings()
        );
    }

    private function enabledConfig(): CategoryAttributeConfigProvider
    {
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);

        return $config;
    }

    #[DataProvider('unavailableMappings')]
    public function testRejectsIneligiblePairBeforePreparingOrFetchingSource(array $mappings): void
    {
        $context = $this->createStub(CategoryFormContextProviderInterface::class);
        $context->method('getForMagentoCategory')->willReturn([
            'category_tree_id' => 7, 'root_category_id' => 2, 'ergonode_category_code' => 'chairs',
        ]);
        $provider = $this->createMock(CategoryDataMappingProvider::class);
        $provider->expects(self::once())->method('clear');
        $provider->expects(self::once())->method('getValidMappingsByCodes')->with(['chairs'])->willReturn($mappings);
        $preparation = $this->createMock(CategoryAttributeSourcePreparation::class);
        $preparation->expects(self::never())->method('prepare');
        $synchronizer = $this->createMock(MappedCategoryAttributeSynchronizer::class);
        $synchronizer->expects(self::never())->method('synchronize');

        $service = new CategoryEntityRefresher(
            $preparation,
            $context,
            $synchronizer,
            $this->lock(),
            $this->enabledConfig(),
            $provider
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The category is excluded from synchronization.');
        $service->refresh(42);
    }

    public function testRepeatedRefreshRechecksEligibilityAndPreservesSynchronizationFailure(): void
    {
        $context = $this->createStub(CategoryFormContextProviderInterface::class);
        $context->method('getForMagentoCategory')->willReturn([
            'category_tree_id' => 7, 'root_category_id' => 2, 'ergonode_category_code' => 'chairs',
        ]);
        $provider = $this->createMock(CategoryDataMappingProvider::class);
        $provider->expects(self::exactly(2))->method('clear');
        $provider->expects(self::exactly(2))->method('getValidMappingsByCodes')->willReturn(
            ['chairs' => [['category_tree_id' => 7, 'magento_category_id' => 42]]],
            []
        );
        $preparation = $this->createMock(CategoryAttributeSourcePreparation::class);
        $preparation->expects(self::once())->method('prepare');
        $synchronizer = $this->createMock(MappedCategoryAttributeSynchronizer::class);
        $failure = new RuntimeException('Unable to fetch category.');
        $synchronizer->expects(self::once())->method('synchronize')->willThrowException($failure);
        $service = new CategoryEntityRefresher(
            $preparation,
            $context,
            $synchronizer,
            $this->lock(),
            $this->enabledConfig(),
            $provider
        );
        try {
            $service->refresh(42);
            self::fail('Expected synchronization failure.');
        } catch (RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The category is excluded from synchronization.');
        $service->refresh(42);
    }

    /** @return array<string, array{array<string, list<array{category_tree_id: int, magento_category_id: int}>>}> */
    public static function unavailableMappings(): array
    {
        return [
            'excluded' => [[]],
            'different source' => [['tables' => [['category_tree_id' => 7, 'magento_category_id' => 42]]]],
            'different tree' => [['chairs' => [['category_tree_id' => 8, 'magento_category_id' => 42]]]],
            'different target' => [['chairs' => [['category_tree_id' => 7, 'magento_category_id' => 43]]]],
        ];
    }

    private function eligibleMappings(): CategoryDataMappingProvider
    {
        $provider = $this->createStub(CategoryDataMappingProvider::class);
        $provider->method('getValidMappingsByCodes')->willReturn(['chairs' => [
            ['category_tree_id' => 8, 'magento_category_id' => 43],
            ['category_tree_id' => 7, 'magento_category_id' => 42],
        ]]);
        return $provider;
    }

    private function lock(): CategorySynchronizationLock
    {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);

        return new CategorySynchronizationLock($manager);
    }
}
