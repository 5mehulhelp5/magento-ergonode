<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateAttributeSetSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateStructureSyncer;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\AttributeSet as AttributeSetFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(AttributeSetFixture::class, ['attribute_set_name' => 'Ergonode target %uniqid%'], as: 'target_set'),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ergonode_template_sync_%uniqid%'], as: 'attribute')
]
class TemplateImportModesIntegrationTest extends TestCase
{
    public function testFullModeCreatesAndMapsPrefixedGroup(): void
    {
        $resource = $this->resource();
        $attributeSetId = (int)$this->fixture('target_set')->getAttributeSetId();
        $attributeId = (int)$this->fixture('attribute')->getAttributeId();
        $attributeCode = (string)$this->fixture('attribute')->getAttributeCode();
        $this->seedTemplate(
            'full-template',
            'product_details',
            'Product Details',
            'full-attribute',
            $attributeSetId,
            $attributeCode
        );
        $groupCountBefore = $this->countAttributeGroups($attributeSetId);

        Bootstrap::getObjectManager()
            ->get(MagentoTemplateStructureSyncer::class)
            ->syncTemplate('full-template');

        $sectionGroupId = (int)$resource->getConnection()->fetchOne(
            $resource->getConnection()
                ->select()
                ->from($resource->getTableName('ergonode_template_section'), ['attribute_group_id'])
                ->where('template_code = ?', 'full-template')
                ->where('section_code = ?', 'product_details')
        );

        self::assertGreaterThan(0, $sectionGroupId);
        self::assertSame($groupCountBefore + 1, $this->countAttributeGroups($attributeSetId));
        self::assertSame(
            'Ergonode - Product Details',
            $resource->getConnection()->fetchOne(
                $resource->getConnection()
                    ->select()
                    ->from($resource->getTableName('eav_attribute_group'), ['attribute_group_name'])
                    ->where('attribute_group_id = ?', $sectionGroupId)
            )
        );
        self::assertSame($sectionGroupId, $this->getAttributeGroupId($attributeSetId, $attributeId));
    }

    public function testAttributesOnlyModeUsesCommonErgonodeGroupWithoutMappingSections(): void
    {
        $resource = $this->resource();
        $attributeSetId = (int)$this->fixture('target_set')->getAttributeSetId();
        $attributeId = (int)$this->fixture('attribute')->getAttributeId();
        $attributeCode = (string)$this->fixture('attribute')->getAttributeCode();
        $this->seedTemplate(
            'attributes-template',
            'details',
            'Details',
            'attributes-only',
            $attributeSetId,
            $attributeCode
        );
        $groupCountBefore = $this->countAttributeGroups($attributeSetId);
        self::assertSame(0, $this->getAttributeGroupId($attributeSetId, $attributeId));

        Bootstrap::getObjectManager()
            ->get(MagentoTemplateAttributeSetSyncer::class)
            ->syncTemplate('attributes-template');

        self::assertSame($groupCountBefore + 1, $this->countAttributeGroups($attributeSetId));
        $commonGroupId = $this->attributeGroupIdByCode($attributeSetId, 'ergonode');
        self::assertGreaterThan(0, $commonGroupId);
        self::assertSame(
            $commonGroupId,
            $this->getAttributeGroupId($attributeSetId, $attributeId)
        );
        self::assertSame('Ergonode', $this->attributeGroupName($commonGroupId));
        self::assertSame(
            0,
            (int)$resource->getConnection()->fetchOne(
                $resource->getConnection()
                    ->select()
                    ->from($resource->getTableName('ergonode_template_section'), ['attribute_group_id'])
                    ->where('template_code = ?', 'attributes-template')
                    ->where('section_code = ?', 'details')
            )
        );
    }

    private function seedTemplate(
        string $templateCode,
        string $sectionCode,
        string $sectionName,
        string $ergonodeAttributeCode,
        int $attributeSetId,
        string $magentoAttributeCode
    ): void {
        $resource = $this->resource();
        $connection = $resource->getConnection();
        $hash = hash('sha256', $templateCode);

        $connection->insert($resource->getTableName('ergonode_template'), [
            'code' => $templateCode,
            'attribute_set_id' => $attributeSetId,
            'content_hash' => $hash,
            'raw_json' => '{}',
        ]);
        $connection->insert($resource->getTableName('ergonode_template_section'), [
            'template_code' => $templateCode,
            'section_code' => $sectionCode,
            'attribute_group_id' => null,
            'sort_order' => 10,
            'content_hash' => $hash,
            'raw_json' => json_encode([
                'name' => [
                    ['language' => 'en_US', 'value' => $sectionName],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        $connection->insert($resource->getTableName('ergonode_template_attribute'), [
            'template_code' => $templateCode,
            'section_code' => $sectionCode,
            'attribute_code' => $ergonodeAttributeCode,
            'sort_order' => 1,
        ]);
        $connection->insert($resource->getTableName('ergonode_product_attribute_mapping'), [
            'ergonode_attribute_code' => $ergonodeAttributeCode,
            'magento_attribute_code' => $magentoAttributeCode,
            'ergonode_type' => 'TEXT',
            'magento_type' => 'text',
            'status' => 'complete',
            'content_hash' => $hash,
            'sort_order' => 1,
        ]);
    }

    private function countAttributeGroups(int $attributeSetId): int
    {
        $resource = $this->resource();

        return (int)$resource->getConnection()->fetchOne(
            $resource->getConnection()
                ->select()
                ->from($resource->getTableName('eav_attribute_group'), ['COUNT(*)'])
                ->where('attribute_set_id = ?', $attributeSetId)
        );
    }

    private function getAttributeGroupId(int $attributeSetId, int $attributeId): int
    {
        $resource = $this->resource();

        return (int)$resource->getConnection()->fetchOne(
            $resource->getConnection()
                ->select()
                ->from($resource->getTableName('eav_entity_attribute'), ['attribute_group_id'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_id = ?', $attributeId)
        );
    }

    private function attributeGroupIdByCode(int $attributeSetId, string $groupCode): int
    {
        $resource = $this->resource();

        return (int)$resource->getConnection()->fetchOne(
            $resource->getConnection()
                ->select()
                ->from($resource->getTableName('eav_attribute_group'), ['attribute_group_id'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_group_code = ?', $groupCode)
        );
    }

    private function attributeGroupName(int $attributeGroupId): string
    {
        $resource = $this->resource();

        return (string)$resource->getConnection()->fetchOne(
            $resource->getConnection()
                ->select()
                ->from($resource->getTableName('eav_attribute_group'), ['attribute_group_name'])
                ->where('attribute_group_id = ?', $attributeGroupId)
        );
    }

    private function resource(): ResourceConnection
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class);
    }

    private function fixture(string $alias): DataObject
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($alias);
        self::assertNotNull($fixture);

        return $fixture;
    }
}
