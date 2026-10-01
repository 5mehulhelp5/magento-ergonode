<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\AttributeConsumer\Api\AttributeBatchImporterInterface;
use Ergonode\AttributeConsumer\Api\OptionSynchronizationInterface;
use Ergonode\AttributeConsumer\Model\Import\AttributeBatchImporter;
use Ergonode\AttributeConsumer\Model\Sync\OptionSynchronizationPool;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationBatchInterface;
use Ergonode\ProductAttributeConsumer\Model\Sync\AttributeSynchronizationBatch;
use Ergonode\ProductAttributeConsumer\Model\Sync\ProductOptionSynchronizationParticipant;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 */
class SharedAttributeContractsTest extends TestCase
{
    public function testMagentoResolvesContractsAndInjectsTheRegisteredProductParticipant(): void
    {
        $objects = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objects);
        self::assertInstanceOf(AttributeBatchImporter::class, $objects->create(AttributeBatchImporterInterface::class));
        self::assertInstanceOf(
            AttributeSynchronizationBatch::class,
            $objects->create(AttributeSynchronizationBatchInterface::class)
        );
        $original = $objects->get(ProductOptionSynchronizationParticipant::class);
        $participant = $this->createMock(ProductOptionSynchronizationParticipant::class);
        $participant->expects(self::once())->method('executeForAttributeCodes')->with(['color'])
            ->willReturn(['mappings' => [['mapping_id' => 17]], 'summary' => ['linked' => 1]]);
        $objects->addSharedInstance($participant, ProductOptionSynchronizationParticipant::class);

        try {
            $pool = $objects->create(OptionSynchronizationInterface::class);
            self::assertInstanceOf(OptionSynchronizationPool::class, $pool);
            $result = $pool->executeForAttributeCodes([' color ', 'color', '']);

            self::assertSame([['mapping_id' => 17]], $result['mappings']);
            self::assertSame(1, $result['summary']['linked']);
        } finally {
            $objects->addSharedInstance($original, ProductOptionSynchronizationParticipant::class);
        }
    }
}
