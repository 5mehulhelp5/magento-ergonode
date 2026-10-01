<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Import\TemplateStructureCacheWriter;
use Ergonode\TemplateAttributeConsumer\Model\Import\TemplateStructureNormalizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateStructureSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Template\UnassignedTemplateSection;
use Ergonode\TemplateConsumer\Model\Import\TemplateCacheWriter;
use Ergonode\TemplateConsumer\Model\Import\TemplateNormalizer;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\AttributeSet as AttributeSetFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(AttributeSetFixture::class, ['attribute_set_name' => 'Unassigned %uniqid%'], as: 'set'),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ergonode_root_%uniqid%'], as: 'root'),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ergonode_real_section_%uniqid%'], as: 'real')
]
class UnassignedTemplateAttributesIntegrationTest extends TestCase
{
    public function testMappedRootAndRealErgonodeSectionCreateDistinctIdempotentGroups(): void
    {
        $attributeSetId = (int)$this->fixture('set')->getAttributeSetId();
        $rootAttribute = $this->fixture('root');
        $realSectionAttribute = $this->fixture('real');
        $template = [
            'code' => 'mixed-root-template',
            'attributeList' => ['edges' => [
                ['node' => ['code' => 'mapped-root']],
                ['node' => ['code' => 'unmapped-root']],
                ['node' => ['code' => 'missing-magento-root']],
            ]],
            'sectionList' => ['edges' => [[
                'node' => [
                    'code' => 'ergonode',
                    'name' => [['language' => 'en_US', 'value' => 'Product Data']],
                    'attributeList' => ['edges' => [
                        ['node' => ['code' => 'real-section-attribute']],
                    ]],
                ],
            ]]],
        ];
        self::assertSame(['mixed-root-template' => 'inserted'], $this->importTemplate($template));
        $this->insertMapping('mapped-root', (string)$rootAttribute->getAttributeCode());
        $this->insertMapping('missing-magento-root', 'ergonode_missing_attribute');
        $this->insertMapping('real-section-attribute', (string)$realSectionAttribute->getAttributeCode());
        $this->cacheProvider()->assignAttributeSet('mixed-root-template', $attributeSetId);

        $firstSync = $this->syncer()->syncTemplate('mixed-root-template');
        $sections = $this->sections('mixed-root-template');
        $synthetic = $sections[UnassignedTemplateSection::CODE];
        $real = $sections['ergonode'];

        self::assertSame(2, $firstSync['groups_created']);
        self::assertSame(2, $firstSync['attributes_skipped']);
        self::assertSame(1, $synthetic['is_synthetic']);
        self::assertSame(0, $real['is_synthetic']);
        self::assertSame(
            UnassignedTemplateSection::GROUP_NAME,
            $this->groupName((int)$synthetic['attribute_group_id'])
        );
        self::assertSame('Ergonode - Product Data', $this->groupName((int)$real['attribute_group_id']));
        self::assertSame(
            (int)$synthetic['attribute_group_id'],
            $this->attributeGroupId($attributeSetId, (int)$rootAttribute->getAttributeId())
        );
        self::assertSame(
            (int)$real['attribute_group_id'],
            $this->attributeGroupId($attributeSetId, (int)$realSectionAttribute->getAttributeId())
        );

        $groupCount = $this->groupCount($attributeSetId);
        self::assertSame(['mixed-root-template' => 'unchanged'], $this->importTemplate($template));
        $secondSync = $this->syncer()->syncTemplate('mixed-root-template');
        self::assertSame(0, $secondSync['groups_created']);
        self::assertSame(0, $secondSync['groups_updated']);
        self::assertSame(2, $secondSync['attributes_unchanged']);
        self::assertSame($groupCount, $this->groupCount($attributeSetId));
    }

    public function testUnmappedAndMissingMagentoRootAttributesDoNotCreateEmptyGroup(): void
    {
        $attributeSetId = (int)$this->fixture('set')->getAttributeSetId();
        $this->importTemplate([
            'code' => 'skipped-root-template',
            'attributeList' => ['edges' => [
                ['node' => ['code' => 'unmapped-root']],
                ['node' => ['code' => 'missing-magento-root']],
            ]],
            'sectionList' => ['edges' => []],
        ]);
        $this->insertMapping('missing-magento-root', 'ergonode_missing_attribute');
        $this->cacheProvider()->assignAttributeSet('skipped-root-template', $attributeSetId);
        $groupCount = $this->groupCount($attributeSetId);

        $sync = $this->syncer()->syncTemplate('skipped-root-template');

        self::assertSame(0, $sync['groups_created']);
        self::assertSame(1, $sync['groups_skipped']);
        self::assertSame(2, $sync['attributes_skipped']);
        self::assertSame($groupCount, $this->groupCount($attributeSetId));
        self::assertNull(
            $this->sections('skipped-root-template')[UnassignedTemplateSection::CODE]['attribute_group_id']
        );
    }

    /**
     * @return array<string, array{attribute_group_id: int|null, is_synthetic: int}>
     */
    private function sections(string $templateCode): array
    {
        $rows = $this->connection()->fetchAll(
            $this->connection()
                ->select()
                ->from(
                    $this->table('ergonode_template_section'),
                    ['section_code', 'attribute_group_id', 'is_synthetic']
                )
                ->where('template_code = ?', $templateCode)
        );
        $sections = [];
        foreach ($rows as $row) {
            $sections[(string)$row['section_code']] = [
                'attribute_group_id' => $row['attribute_group_id'] !== null
                    ? (int)$row['attribute_group_id']
                    : null,
                'is_synthetic' => (int)$row['is_synthetic'],
            ];
        }

        return $sections;
    }

    private function insertMapping(string $ergonodeCode, string $magentoCode): void
    {
        $this->connection()->insert($this->table('ergonode_product_attribute_mapping'), [
            'ergonode_attribute_code' => $ergonodeCode,
            'magento_attribute_code' => $magentoCode,
            'ergonode_type' => 'TEXT',
            'magento_type' => 'text',
            'status' => 'complete',
            'content_hash' => hash('sha256', $ergonodeCode),
            'sort_order' => 1,
        ]);
    }

    private function groupName(int $attributeGroupId): string
    {
        return (string)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('eav_attribute_group'), ['attribute_group_name'])
                ->where('attribute_group_id = ?', $attributeGroupId)
        );
    }

    private function attributeGroupId(int $attributeSetId, int $attributeId): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('eav_entity_attribute'), ['attribute_group_id'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_id = ?', $attributeId)
        );
    }

    private function groupCount(int $attributeSetId): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('eav_attribute_group'), ['COUNT(*)'])
                ->where('attribute_set_id = ?', $attributeSetId)
        );
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, string>
     */
    private function importTemplate(array $template): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $normalizer = $objectManager->get(TemplateNormalizer::class);
        $objectManager->get(TemplateCacheWriter::class)->save([$normalizer->normalize($template)]);
        $structure = $objectManager->get(TemplateStructureNormalizer::class)->normalize($template);

        return $objectManager->get(TemplateStructureCacheWriter::class)->save([$structure]);
    }

    private function cacheProvider(): TemplateCacheProvider
    {
        return Bootstrap::getObjectManager()->get(TemplateCacheProvider::class);
    }

    private function syncer(): MagentoTemplateStructureSyncer
    {
        return Bootstrap::getObjectManager()->get(MagentoTemplateStructureSyncer::class);
    }

    private function connection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function table(string $name): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getTableName($name);
    }

    private function fixture(string $alias): DataObject
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($alias);
        self::assertNotNull($fixture);

        return $fixture;
    }
}
