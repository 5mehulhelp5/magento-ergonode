<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Integration;

use Ergonode\CategoryAttributePublisher\Api\CategoryAttributeStateFactoryInterface;
use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributePublisher\Model\Data\CategoryAttributeStateFactory;
use Ergonode\CategoryAttributePublisher\Model\GraphQl\WriteScopeCategoryAttributeCodeLoader;
use Ergonode\CategoryAttributePublisher\Model\Sync\CategoryAttributeSynchronizationContributor;
use Ergonode\CategoryPublisher\Model\Sync\CategorySyncPlanner;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class DependencyInjectionConfigIntegrationTest extends TestCase
{
    public function testWriteLoaderResolvesToPublisherImplementation(): void
    {
        self::assertInstanceOf(
            WriteScopeCategoryAttributeCodeLoader::class,
            Bootstrap::getObjectManager()->get(WriteScopeCategoryAttributeCodeLoaderInterface::class)
        );
    }

    public function testAttributeStateFactoryAndPlannerContributorAreRegistered(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        self::assertInstanceOf(
            CategoryAttributeStateFactory::class,
            $objectManager->get(CategoryAttributeStateFactoryInterface::class)
        );
        $planner = $objectManager->get(CategorySyncPlanner::class);
        $reflection = new ReflectionProperty($planner, 'contributors');
        self::assertContainsOnlyInstancesOf(
            CategoryAttributeSynchronizationContributor::class,
            $reflection->getValue($planner)
        );
    }
}
