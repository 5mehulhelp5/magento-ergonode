<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Model\Sync;

use Ergonode\ProductCategoryPublisher\Model\Config\CategoryPublicationConfig;
use Ergonode\ProductCategoryPublisher\Model\Data\ProductCategoryState;
use Ergonode\ProductCategoryPublisher\Model\Sync\ProductCategoryPublicationPolicy;
use Ergonode\ProductCategoryPublisher\Model\Sync\ProductCategoryPublicationSynchronizer;
use Ergonode\ProductCategoryPublisher\Model\Sync\ProductCategorySynchronizer;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\Sync\ProductSynchronizer;
use PHPUnit\Framework\TestCase;

class ProductCategorySynchronizerTest extends TestCase
{
    public function testMatchModeBlocksIncompleteCategorySourceBeforeBaseMutations(): void
    {
        $state = new ProductCategoryState(
            new ProductState('SKU-1', 'simple', 'default'),
            ['chairs'],
            false
        );
        $config = $this->createStub(CategoryPublicationConfig::class);
        $config->method('shouldRemoveMissingCategories')->willReturn(true);
        $base = $this->createMock(ProductSynchronizer::class);
        $base->expects(self::never())->method('synchronizeBatch');
        $categories = $this->createMock(ProductCategoryPublicationSynchronizer::class);
        $categories->expects(self::never())->method('synchronize');

        $result = (new ProductCategorySynchronizer(
            $base,
            $categories,
            new ProductCategoryPublicationPolicy($config)
        ))->synchronize($state);

        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $result->getStatus());
        self::assertStringContainsString('not mapped to categories', $result->getMessage());
    }
}
