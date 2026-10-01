<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Mapping;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingDataPreparer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryMappingDataPreparerTest extends TestCase
{
    public static function configuredWork(): array
    {
        return ['legacy' => [null, true], 'enabled' => [true, true], 'no work' => [false, false]];
    }

    #[DataProvider('configuredWork')]
    public function testPreparesOnlyConfiguredWorkAndDoesNotWriteValues(?bool $work, bool $expected): void
    {
        $sync = $work === null ? $this->createMock(CategoryEntitySynchronizerInterface::class)
            : $this->createMockForIntersectionOfInterfaces([
                CategoryEntitySynchronizerInterface::class, CategoryDataWorkProviderInterface::class,
            ]);
        $sync->expects(self::never())->method('synchronize');
        if ($work !== null) {
            $sync->expects(self::once())->method('hasWork')->willReturn($work);
        }
        $entity = ['code' => 'chairs', 'labels' => [], 'attributes' => [], 'hash' => '', 'raw' => []];
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects($expected ? self::once() : self::never())->method('loadMany')
            ->with(['chairs'])->willReturn(['chairs' => $entity]);
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $preparer = new CategoryMappingDataPreparer($loader, $sync, $config);
        self::assertSame(
            $expected ? [['category_id' => 42, 'entity' => $entity]] : [],
            $preparer->prepare(['chairs' => 42])
        );
    }
}
