<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Provider;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoOptionLabelSyncer;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class MagentoOptionLabelSyncerTest extends TestCase
{
    public function testSyncLabelsBatchUsesTwoReadsAndOneUpsertForAllOptions(): void
    {
        $optionSelect = $this->createMock(Select::class);
        $optionSelect->expects($this->once())
            ->method('from')
            ->with('eav_attribute_option', ['option_id'])
            ->willReturnSelf();
        $optionSelect->expects($this->exactly(2))->method('where')->willReturnSelf();

        $valueSelect = $this->createMock(Select::class);
        $valueSelect->expects($this->once())
            ->method('from')
            ->with('eav_attribute_option_value', ['option_id', 'store_id', 'value'])
            ->willReturnSelf();
        $valueSelect->expects($this->exactly(2))->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls($optionSelect, $valueSelect);
        $connection->expects($this->once())->method('fetchCol')->with($optionSelect)->willReturn([10, 11]);
        $connection->expects($this->once())->method('fetchAll')->with($valueSelect)->willReturn([
            ['option_id' => 10, 'store_id' => 0, 'value' => 'Bez zmian'],
            ['option_id' => 10, 'store_id' => 12, 'value' => 'Old English'],
        ]);
        $connection->expects($this->once())->method('insertOnDuplicate')->with(
            'eav_attribute_option_value',
            [
                ['option_id' => 10, 'store_id' => 12, 'value' => 'New English'],
                ['option_id' => 11, 'store_id' => 0, 'value' => 'Nowa'],
                ['option_id' => 11, 'store_id' => 12, 'value' => 'Nowa'],
            ],
            ['value']
        );

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getAdminLanguageCode')->willReturn('pl_PL');
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL', 12 => 'en_US']);
        $syncer = new MagentoOptionLabelSyncer(
            $resource,
            $languageMappingProvider
        );

        $changes = $syncer->syncLabelsBatch(41, [
            10 => ['pl_PL' => 'Bez zmian', 'en_US' => 'New English'],
            11 => ['pl_PL' => 'Nowa'],
        ]);

        self::assertSame([10 => 1, 11 => 2], $changes);
    }
}
