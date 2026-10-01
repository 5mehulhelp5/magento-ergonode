<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Integration;

use Ergonode\CategoryPublisher\Model\Sync\CategorySynchronizer;
use Ergonode\CategoryPublisher\Model\Sync\StrictCategoryCreator;
use Ergonode\Publisher\Model\GraphQl\MutationExecutor;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class DependencyInjectionConfigIntegrationTest extends TestCase
{
    public function testStrictCreateUsesImmediateExecutorFromActualObjectGraph(): void
    {
        $publisher = Bootstrap::getObjectManager()->create(CategoryBatchPublisher::class);
        $synchronizer = (new ReflectionProperty(
            CategoryBatchPublisher::class,
            'batchSynchronizer'
        ))->getValue($publisher);
        $creator = (new ReflectionProperty(CategorySynchronizer::class, 'strictCreator'))->getValue($synchronizer);
        $executor = (new ReflectionProperty(StrictCategoryCreator::class, 'executor'))->getValue($creator);
        self::assertSame(1, (new ReflectionProperty(MutationExecutor::class, 'maxAttempts'))->getValue($executor));
        self::assertSame(0, (new ReflectionProperty(MutationExecutor::class, 'baseDelaySeconds'))->getValue($executor));
    }
}
