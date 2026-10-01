<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Test\Integration\Model;

use Ergonode\TemplateAttribute\Api\StructureProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class StructureProviderIntegrationTest extends TestCase
{
    public function testSeparatesAttributesByPlacementInTheSelectedProductSet(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $typeId = (int)$connection->fetchOne($connection->select()
            ->from($resource->getTableName('eav_entity_type'), ['entity_type_id'])
            ->where('entity_type_code = ?', 'catalog_product'));
        $setId = (int)$connection->fetchOne($connection->select()
            ->from($resource->getTableName('eav_attribute_set'), ['attribute_set_id'])
            ->where('entity_type_id = ?', $typeId)->order('attribute_set_id ASC'));
        $connection->update(
            $resource->getTableName('ergonode_template'),
            ['attribute_set_id' => null],
            ['attribute_set_id = ?' => $setId]
        );
        $connection->insert($resource->getTableName('ergonode_template'), [
            'code' => 'structure-preview-fixture', 'attribute_set_id' => $setId,
            'raw_json' => '{}', 'content_hash' => hash('sha256', '{}'),
        ]);
        $before = $connection->fetchAll($connection->select()->from($resource->getTableName('eav_entity_attribute')));
        $result = Bootstrap::getObjectManager()->get(StructureProviderInterface::class)
            ->get('structure-preview-fixture', $setId);
        $expected = $connection->fetchCol($connection->select()
            ->from(['a' => $resource->getTableName('eav_attribute')], ['attribute_code'])
            ->joinInner(['p' => $resource->getTableName('eav_entity_attribute')], 'p.attribute_id = a.attribute_id', [])
            ->where('p.attribute_set_id = ?', $setId)->where('a.entity_type_id = ?', $typeId));
        $assigned = array_column(array_filter(
            $result['attributes'],
            static fn (array $attribute): bool => $attribute['attribute_group_id'] !== null
        ), 'attribute_code');
        sort($assigned);
        sort($expected);
        self::assertSame($expected, $assigned);
        self::assertSame($before, $connection->fetchAll($connection->select()
            ->from($resource->getTableName('eav_entity_attribute'))));
        self::assertSame([], $result['sections']);
        self::assertSame([], $result['sourceAttributes']);
    }
}
