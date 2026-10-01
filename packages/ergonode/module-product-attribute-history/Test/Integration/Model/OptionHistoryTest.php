<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Integration\Model;

use Ergonode\AttributeConsumer\Api\OptionSnapshotRemoverInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingSaver;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryQueryInterface;
use Ergonode\ProductAttributeHistory\Model\SnapshotProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class OptionHistoryTest extends TestCase
{
    public function testSavesOptionMappingVisibilityAndImmutableMetadataThenCapturesRemoval(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $attributeCode = 'history_option_attribute';
        $objects->create(EavSetup::class)->addAttribute('catalog_product', 'history_option_target', [
            'type' => 'int', 'input' => 'boolean', 'label' => 'History target',
            'source' => Boolean::class,
            'user_defined' => true, 'visible' => true, 'required' => false,
        ]);
        $connection->insert($resource->getTableName('ergonode_attribute'), [
            'code' => $attributeCode, 'type' => 'select', 'scope' => 'global',
            'labels_json' => '{"en_US":"Historical choices"}', 'parameters_json' => '{}',
            'content_hash' => hash('sha256', $attributeCode),
        ]);
        $optionTable = $resource->getTableName('ergonode_attribute_option');
        $connection->insert($optionTable, [
            'attribute_code' => $attributeCode, 'option_code' => 'yes',
            'labels_json' => '{"en_US":"Original yes"}', 'content_hash' => hash('sha256', 'yes'),
        ]);
        $objects->get(AttributeMappingSaver::class)->saveAdditions([[
            'left' => ['code' => $attributeCode, 'type' => 'select'],
            'right' => ['code' => 'history_option_target', 'type' => 'boolean'],
        ]]);
        $rows = $objects->get(MappingReaderInterface::class)->getAttributeRows();
        $row = array_values(array_filter($rows, static fn (array $row): bool =>
            $row['ergonode_attribute_code'] === $attributeCode))[0];
        $mappingId = (int)$row['mapping_id'];
        $query = $objects->get(HistoryQueryInterface::class);
        $count = $query->getOperations()['total'];
        $saver = $objects->get(OptionMappingSaver::class);
        $saver->save($mappingId, [[
            'left' => ['code' => 'yes'], 'right' => ['code' => 'option_1'],
        ]], []);
        $page = $query->getOperations(1);
        self::assertSame($count + 1, $page['total']);
        self::assertSame('save_options', $page['items'][0]['operation_code']);
        $savedId = $page['items'][0]['operation_id'];
        $saved = $query->getState($savedId);
        self::assertSame('option_1', $saved['options']['source'][$attributeCode][0]['mapped_code']);
        self::assertSame(
            'history_option_target',
            $saved['options']['source'][$attributeCode][0]['mapped_attribute_code']
        );
        $changes = array_values(array_filter($saved['changes'], static fn (array $change): bool =>
            ($change['entity'] ?? '') === 'option'));
        self::assertCount(2, $changes);
        self::assertSame(['connected'], $changes[0]['actions']);

        $saver->save($mappingId, [], [['source' => 'ergo', 'code' => 'yes', 'active' => false]]);
        $unmapped = $query->getState($query->getOperations(1)['items'][0]['operation_id']);
        self::assertNull($unmapped['options']['source'][$attributeCode][0]['mapped_code']);
        self::assertFalse($unmapped['options']['source'][$attributeCode][0]['active']);
        self::assertSame(['excluded', 'disconnected'], $unmapped['changes'][0]['actions']);

        $objects->get(HistoryOperationCaptureInterface::class)->execute('refresh_options', static function () use (
            $connection,
            $optionTable,
            $attributeCode
        ): void {
            $connection->update(
                $optionTable,
                ['labels_json' => '{"en_US":"Renamed yes"}'],
                ['attribute_code = ?' => $attributeCode]
            );
        });
        $renamed = $query->getState($query->getOperations(1)['items'][0]['operation_id']);
        self::assertSame(['renamed'], $renamed['changes'][0]['actions']);
        self::assertSame('Original yes', $renamed['changes'][0]['before']['label']);
        self::assertSame('Renamed yes', $renamed['changes'][0]['after']['label']);
        self::assertSame($saved, $query->getState($savedId));

        $objects->get(OptionSnapshotRemoverInterface::class)->remove($attributeCode, 'yes');
        $removed = $query->getState($query->getOperations(1)['items'][0]['operation_id']);
        self::assertSame('delete_option_snapshot', $removed['operation']['operation_code']);
        self::assertSame([], $removed['options']['source'][$attributeCode]);
        self::assertSame(['deleted'], $removed['changes'][0]['actions']);
        self::assertSame($saved, $query->getState($savedId));
    }

    public function testMagentoOptionLabelsAreReadFreshWithinOneRequest(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $attributeId = (int)$connection->fetchOne($connection->select()
            ->from($resource->getTableName('eav_attribute'), ['attribute_id'])
            ->where('attribute_code = ?', 'manufacturer'));
        $connection->insert($resource->getTableName('eav_attribute_option'), ['attribute_id' => $attributeId]);
        $optionId = (int)$connection->lastInsertId();
        $valueTable = $resource->getTableName('eav_attribute_option_value');
        $connection->insert($valueTable, ['option_id' => $optionId, 'store_id' => 0, 'value' => 'Before']);
        $provider = $objects->get(SnapshotProvider::class);
        $before = $provider->getState();
        $connection->update($valueTable, ['value' => 'After'], ['option_id = ?' => $optionId]);
        $after = $provider->getState();
        $beforeOptions = array_column($before['options']['target']['manufacturer'], null, 'code');
        $afterOptions = array_column($after['options']['target']['manufacturer'], null, 'code');
        self::assertSame('Before', $beforeOptions['option_' . $optionId]['label']);
        self::assertSame('After', $afterOptions['option_' . $optionId]['label']);
    }
}
