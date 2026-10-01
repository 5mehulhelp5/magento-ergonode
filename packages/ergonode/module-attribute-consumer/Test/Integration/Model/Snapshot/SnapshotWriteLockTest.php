<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Integration\Model\Snapshot;

use Ergonode\AttributeConsumer\Model\Import\AttributeCacheRefresher;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Import\OptionCacheReconciler;
use Ergonode\AttributeConsumer\Model\ResourceModel\AttributeDefinitionSnapshot;
use Ergonode\AttributeConsumer\Model\Snapshot\AttributeSnapshotRemover;
use Ergonode\AttributeConsumer\Model\Snapshot\OptionSnapshotRemover;
use Ergonode\AttributeConsumer\Model\Snapshot\SnapshotWriteLock;
use Ergonode\AttributeConsumer\Plugin\SnapshotWriteLockPlugin;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 */
class SnapshotWriteLockTest extends TestCase
{
    public function testEveryWriterRejectsContentionBeforeDatabaseOrHttpWork(): void
    {
        $manager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $manager);
        $backend = $this->createMock(LockManagerInterface::class);
        $backend->expects(self::exactly(8))->method('lock')->willReturn(false);
        $backend->expects(self::never())->method('unlock');
        $manager->addSharedInstance(
            new SnapshotWriteLockPlugin(new SnapshotWriteLock($backend)),
            SnapshotWriteLockPlugin::class
        );
        $calls = [
            [AttributeCacheRefresher::class, 'refreshOptions', ['color']],
            [AttributeCacheRefresher::class, 'refreshOptionsWriteScope', ['color']],
            [AttributeCacheWriter::class, 'saveAttributes', [[]]],
            [AttributeCacheWriter::class, 'saveOptions', ['color', []]],
            [AttributeDefinitionSnapshot::class, 'replace', [[], [
                'source' => 'test', 'attribute' => null, 'deleted' => null,
            ]]],
            [OptionCacheReconciler::class, 'reconcile', ['color', []]],
            [AttributeSnapshotRemover::class, 'remove', ['color']],
            [OptionSnapshotRemover::class, 'remove', ['color', 'red']],
        ];
        foreach ($calls as [$class, $method, $arguments]) {
            try {
                $manager->create($class)->$method(...$arguments);
                self::fail($class . ' bypassed snapshot writer coordination.');
            } catch (LocalizedException $exception) {
                self::assertStringContainsString(
                    'snapshot synchronization is already running',
                    $exception->getMessage()
                );
            }
        }
    }
}
