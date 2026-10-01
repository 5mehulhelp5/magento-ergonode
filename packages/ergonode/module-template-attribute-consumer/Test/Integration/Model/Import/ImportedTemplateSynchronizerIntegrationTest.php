<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Integration\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Import\ImportedTemplateSynchronizer;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetResource;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetSynchronizer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class ImportedTemplateSynchronizerIntegrationTest extends TestCase
{
    #[
        Config('ergonode_templates/import/sync_attributes', '1'),
        Config('ergonode_templates/import/sync_sections', '1'),
        Config('ergonode_templates/import/create_attribute_sets', '1')
    ]
    public function testRecreatesDeletedAttributeSetAndSecondRunIsIdempotent(): void
    {
        $staleAttributeSetId = $this->createAttributeSetRow('Deleted set for recreate');
        $this->seedTemplate('stale-recreate-template', $staleAttributeSetId, 60001);
        $this->deleteAttributeSetRow($staleAttributeSetId);
        $report = $this->changeReport();
        $report->reset();

        $this->synchronizeImportedTemplates(['stale-recreate-template']);

        $newAttributeSetId = $this->templateAttributeSetId('stale-recreate-template');
        self::assertNotNull($newAttributeSetId);
        self::assertNotSame($staleAttributeSetId, $newAttributeSetId);
        self::assertTrue($this->attributeSetResource()->productAttributeSetExists($newAttributeSetId));
        self::assertSame(60001, $this->sectionGroupId('stale-recreate-template'));
        self::assertSame(
            ['lost_attribute_set_id' => $staleAttributeSetId],
            $this->reportEntry(
                $report->getEntries(true),
                'template_attribute_set',
                'stale-recreate-template',
                'Cleared stale Magento attribute set mapping during template import.'
            )['details']
        );

        $report->reset();
        $this->synchronizeImportedTemplates(['stale-recreate-template']);

        self::assertSame($newAttributeSetId, $this->templateAttributeSetId('stale-recreate-template'));
        self::assertSame(
            [],
            array_values(array_filter(
                $report->getEntries(true),
                static fn (array $entry): bool => $entry['message']
                    === 'Cleared stale Magento attribute set mapping during template import.'
            ))
        );
    }

    #[
        Config('ergonode_templates/import/sync_attributes', '1'),
        Config('ergonode_templates/import/sync_sections', '1'),
        Config('ergonode_templates/import/create_attribute_sets', '0')
    ]
    public function testClearsAndSkipsDeletedSetThenContinuesWithNextTemplate(): void
    {
        $staleAttributeSetId = $this->createAttributeSetRow('Deleted set for skip');
        $validAttributeSetId = $this->attributeSetResource()->getDefaultProductAttributeSetId();
        $this->seedTemplate('stale-skip-template', $staleAttributeSetId, 60002);
        $this->seedTemplate('valid-next-template', $validAttributeSetId, null);
        $this->deleteAttributeSetRow($staleAttributeSetId);
        $report = $this->changeReport();
        $report->reset();

        $this->synchronizeImportedTemplates(['stale-skip-template', 'valid-next-template']);

        self::assertNull($this->templateAttributeSetId('stale-skip-template'));
        self::assertSame(60002, $this->sectionGroupId('stale-skip-template'));
        self::assertSame($validAttributeSetId, $this->templateAttributeSetId('valid-next-template'));
        self::assertSame(
            ['lost_attribute_set_id' => $staleAttributeSetId],
            $this->reportEntry(
                $report->getEntries(true),
                'template_attribute_set',
                'stale-skip-template',
                'Cleared stale Magento attribute set mapping during template import.'
            )['details']
        );
        self::assertSame(
            ChangeReport::ACTION_SKIPPED,
            $this->reportEntry(
                $report->getEntries(true),
                'template_group',
                'valid-next-template::details'
            )['action']
        );
    }

    #[
        Config('ergonode_templates/import/sync_attributes', '1'),
        Config('ergonode_templates/import/sync_sections', '0'),
        Config('ergonode_templates/import/create_attribute_sets', '1')
    ]
    public function testAttributesOnlyClearsAndRecreatesDeletedSet(): void
    {
        $staleAttributeSetId = $this->createAttributeSetRow('Deleted set for attributes only');
        $this->seedTemplate('stale-attributes-template', $staleAttributeSetId, 60003);
        $this->deleteAttributeSetRow($staleAttributeSetId);

        $this->synchronizeImportedTemplates(['stale-attributes-template']);

        $replacementAttributeSetId = $this->templateAttributeSetId('stale-attributes-template');
        self::assertNotNull($replacementAttributeSetId);
        self::assertNotSame($staleAttributeSetId, $replacementAttributeSetId);
        self::assertSame(60003, $this->sectionGroupId('stale-attributes-template'));
        self::assertSame(
            $replacementAttributeSetId,
            $this->attributeSetManager()->getExistingAttributeSetIdForTemplate('stale-attributes-template')
        );
    }

    /** @param string[] $templateCodes */
    private function synchronizeImportedTemplates(array $templateCodes): void
    {
        // Import resolves sets in TemplateConsumer before invoking the structure contributor.
        // Empty source sections retain their snapshot metadata; only the set owner repairs set mappings.
        Bootstrap::getObjectManager()->get(TemplateAttributeSetSynchronizer::class)->sync($templateCodes);
        $this->synchronizer()->execute($templateCodes);
    }

    private function seedTemplate(string $templateCode, int $attributeSetId, ?int $attributeGroupId): void
    {
        $hash = hash('sha256', $templateCode);
        $this->connection()->insert($this->table('ergonode_template'), [
            'code' => $templateCode,
            'attribute_set_id' => $attributeSetId,
            'content_hash' => $hash,
            'raw_json' => '{}',
        ]);
        $this->connection()->insert($this->table('ergonode_template_section'), [
            'template_code' => $templateCode,
            'section_code' => 'details',
            'attribute_group_id' => $attributeGroupId,
            'sort_order' => 10,
            'content_hash' => $hash,
            'raw_json' => '{"name":[{"language":"en_US","value":"Details"}]}',
        ]);
    }

    private function createAttributeSetRow(string $name): int
    {
        $table = $this->table('eav_attribute_set');
        $this->connection()->insert($table, [
            'entity_type_id' => $this->attributeSetManager()->getProductEntityTypeId(),
            'attribute_set_name' => $name,
            'sort_order' => 0,
        ]);

        $connection = $this->connection();
        self::assertInstanceOf(Mysql::class, $connection);

        return (int)$connection->lastInsertId($table);
    }

    private function deleteAttributeSetRow(int $attributeSetId): void
    {
        $this->connection()->delete(
            $this->table('eav_attribute_set'),
            ['attribute_set_id = ?' => $attributeSetId]
        );
    }

    private function templateAttributeSetId(string $templateCode): ?int
    {
        $value = $this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('ergonode_template'), ['attribute_set_id'])
                ->where('code = ?', $templateCode)
        );

        return $value !== null && $value !== false ? (int)$value : null;
    }

    private function sectionGroupId(string $templateCode): ?int
    {
        $value = $this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('ergonode_template_section'), ['attribute_group_id'])
                ->where('template_code = ?', $templateCode)
                ->where('section_code = ?', 'details')
        );

        return $value !== null && $value !== false ? (int)$value : null;
    }

    /**
     * @param array<int, array{
     *     entity: string,
     *     identifier: string,
     *     action: string,
     *     message: string,
     *     details: array<string, mixed>
     * }> $entries
     * @return array{
     *     entity: string,
     *     identifier: string,
     *     action: string,
     *     message: string,
     *     details: array<string, mixed>
     * }
     */
    private function reportEntry(
        array $entries,
        string $entity,
        string $identifier,
        ?string $message = null
    ): array {
        foreach ($entries as $entry) {
            if ($entry['entity'] === $entity
                && $entry['identifier'] === $identifier
                && ($message === null || $entry['message'] === $message)
            ) {
                return $entry;
            }
        }

        self::fail(sprintf('Report entry %s:%s was not found.', $entity, $identifier));
    }

    private function synchronizer(): ImportedTemplateSynchronizer
    {
        return Bootstrap::getObjectManager()->get(ImportedTemplateSynchronizer::class);
    }

    private function attributeSetManager(): AttributeSetManager
    {
        return Bootstrap::getObjectManager()->get(AttributeSetManager::class);
    }

    private function attributeSetResource(): AttributeSetResource
    {
        return Bootstrap::getObjectManager()->get(AttributeSetResource::class);
    }

    private function changeReport(): ChangeReport
    {
        return Bootstrap::getObjectManager()->get(ChangeReport::class);
    }

    private function connection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function table(string $name): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getTableName($name);
    }
}
