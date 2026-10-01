<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Sync;

use Ergonode\Category\Model\Import\CategoryTreePageReader;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\CategoryConsumer\Plugin\CategoryTreeDownloadProgress;
use Magento\Framework\Interception\DefinitionInterface;
use Magento\Framework\Interception\PluginListInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true)]
class CategorySynchronizationWiringTest extends TestCase
{
    public function testGlobalDiProvidesPreflightAndTheDownloadObserver(): void
    {
        $manager = Bootstrap::getObjectManager();
        self::assertInstanceOf(CategoryStreamEligibility::class, $manager->get(CategoryStreamEligibility::class));
        $plugins = $manager->get(PluginListInterface::class);
        $chain = $plugins->getNext(CategoryTreePageReader::class, 'read');
        self::assertContains('ergonode_category_download_progress', $chain[DefinitionInterface::LISTENER_AFTER]);
        self::assertInstanceOf(CategoryTreeDownloadProgress::class, $plugins->getPlugin(
            CategoryTreePageReader::class,
            'ergonode_category_download_progress'
        ));
    }
}
