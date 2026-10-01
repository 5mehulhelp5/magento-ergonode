<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Integration\Model\Import;

use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\TemplateAttributeConsumer\Model\GraphQl\TemplateStructureLoader;
use Ergonode\TemplateAttributeConsumer\Model\GraphQl\TemplateStructureQueries;
use Ergonode\TemplateAttributeConsumer\Model\Import\ImportedTemplateProcessor as StructureProcessor;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleaner;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Ergonode\TemplateConsumer\Model\GraphQl\TemplateLoader;
use Ergonode\TemplateConsumer\Model\GraphQl\TemplateQueries;
use Ergonode\TemplateConsumer\Model\Import\ImportedTemplateProcessor;
use Ergonode\TemplateConsumer\Model\Import\TemplateListImporter;
use Ergonode\TemplateConsumer\Model\Import\TemplateListPageReader;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Test\Fixture\Attribute;
use Magento\Catalog\Test\Fixture\AttributeSet;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[
    AppIsolation(true), DbIsolation(true),
    Config('ergonode_templates/import/sync_attributes', '1'),
    Config('ergonode_templates/import/sync_sections', '1'),
    DataFixture(AttributeSet::class, ['attribute_set_name' => 'Lifecycle %uniqid%'], as: 'set'),
    DataFixture(Attribute::class, ['attribute_code' => 'lifecycle_removed_%uniqid%'], as: 'removed'),
    DataFixture(Attribute::class, ['attribute_code' => 'lifecycle_manual_%uniqid%'], as: 'manual'),
    DataFixture(Attribute::class, ['attribute_code' => 'lifecycle_required_%uniqid%'], as: 'required'),
    DataFixture(Attribute::class, ['attribute_code' => 'lifecycle_added_%uniqid%'], as: 'added'),
    DataFixture(Product::class, ['attribute_set_id' => '$set.attribute_set_id$'], as: 'product')
]
class TemplateLifecycleIntegrationTest extends TestCase
{
    private const string TEMPLATE = 'lifecycle-template';
    private array $sections = ['details' => ['removed', 'manual'], 'specification' => ['required']];
    private string $specificationName = 'Specification';
    private bool $deleted = false;
    private bool $incomplete = false;
    private bool $paginateStructure = false;
    private bool $failSecondSectionPage = false;
    private int $templatePageCount = 0;
    private int $detailsPageCount = 0;

    public function testPaginatedSourceWritesTheCompleteSnapshotAndPlacements(): void
    {
        $this->paginateStructure = true;
        $this->prepare();

        self::assertSame(2, $this->templatePageCount);
        self::assertSame(2, $this->detailsPageCount);
        $sectionRows = $this->rows('ergonode_template_section', 'template_code', self::TEMPLATE);
        $sectionCodes = array_column($sectionRows, 'section_code');
        sort($sectionCodes);
        self::assertSame(['details', 'specification'], $sectionCodes);
        $attributeRows = $this->rows('ergonode_template_attribute', 'template_code', self::TEMPLATE);
        $attributeCodes = array_column($attributeRows, 'attribute_code');
        sort($attributeCodes);
        self::assertSame(['manual', 'removed', 'required'], $attributeCodes);
        foreach (['removed', 'manual', 'required'] as $name) {
            self::assertNotNull($this->placement($name));
        }
        self::assertNull($this->placement('added'));
    }

    public function testTransportFailureOnSecondSectionPagePreservesSnapshotAndPlacements(): void
    {
        $this->paginateStructure = true;
        $importer = $this->prepare();
        $beforeSections = $this->rows('ergonode_template_section', 'template_code', self::TEMPLATE);
        $beforeAttributes = $this->rows('ergonode_template_attribute', 'template_code', self::TEMPLATE);
        $beforePlacements = $this->magentoStructure();

        $this->sections['details'] = ['added', 'manual'];
        $this->failSecondSectionPage = true;
        try {
            $importer->execute();
            self::fail('Transport failure was accepted as a complete structure.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('Simulated transport failure', $exception->getMessage());
        }

        self::assertSame($beforeSections, $this->rows('ergonode_template_section', 'template_code', self::TEMPLATE));
        $afterAttributes = $this->rows('ergonode_template_attribute', 'template_code', self::TEMPLATE);
        self::assertSame($beforeAttributes, $afterAttributes);
        self::assertSame($beforePlacements, $this->magentoStructure());
    }

    public function testRefreshOnlyUpdatesSnapshotThenSyncAddsMovesReordersAndRemoves(): void
    {
        $importer = $this->prepare();
        $before = $this->magentoStructure();
        $manualGroup = $this->placement('manual')['attribute_group_id'];
        $this->sections = ['specification' => ['required', 'manual', 'added']];
        $importer->execute(false);
        self::assertSame($before, $this->magentoStructure(), 'Refresh changed Magento.');
        $importer->execute();
        self::assertNull($this->placement('removed'));
        self::assertSame($manualGroup, $this->placement('manual')['attribute_group_id']);
        self::assertSame(
            $this->placement('required')['attribute_group_id'],
            $this->placement('added')['attribute_group_id']
        );
        self::assertSame(3, (int)$this->placement('added')['sort_order']);
        $after = $this->magentoStructure();
        $importer->execute();
        self::assertSame($after, $this->magentoStructure());
    }

    public function testDeletedTemplateCleanupAfterRefreshPreservesRequiredManualProductsAndValues(): void
    {
        $importer = $this->prepare();
        $product = $this->fixture('product');
        $code = (string)$this->fixture('removed')->getAttributeCode();
        $product->setData($code, 'Value survives membership removal');
        $productResource = Bootstrap::getObjectManager()->get(ProductResource::class);
        $productResource->saveAttribute($product, $code);
        $before = $this->magentoStructure();
        $this->deleted = true;
        $importer->execute(false);
        self::assertSame(1, (int)$this->templateRow()['is_deleted']);
        self::assertSame($before, $this->magentoStructure());
        $importer->execute();
        self::assertNull($this->placement('removed'));
        self::assertNotNull($this->placement('required'));
        self::assertNotNull($this->placement('manual'));
        self::assertSame($this->setId(), (int)$this->templateRow()['attribute_set_id']);
        self::assertSame('Value survives membership removal', $productResource->getAttributeRawValue(
            (int)$product->getId(),
            $code,
            0
        ));
        self::assertSame($before['system'], $this->magentoStructure()['system']);
        foreach (['ergonode_template_group_ownership', 'ergonode_template_attribute_ownership'] as $table) {
            self::assertSame([], $this->rows($table, 'template_code', self::TEMPLATE));
        }
        $after = $this->magentoStructure();
        $importer->execute();
        self::assertSame($after, $this->magentoStructure());
        $persistedProduct = $this->rows('catalog_product_entity', 'entity_id', $product->getId())[0];
        self::assertSame($this->setId(), (int)$persistedProduct['attribute_set_id']);
    }

    #[DbIsolation(false)]
    public function testFailedCleanupRollsBackAndNextSynchronizationRetriesEarlierDeletion(): void
    {
        // Exercise a real database rollback, without the fixture framework's outer transaction.
        try {
            $importer = $this->prepare();
            $this->deleted = true;
            $importer->execute(false);
            $before = $this->magentoStructure();
            $objectManager = Bootstrap::getObjectManager();
            $ownership = $this->getMockBuilder(TemplateStructureOwnershipResource::class)
                ->setConstructorArgs([$objectManager->get(ResourceConnection::class)])
                ->onlyMethods(['deleteGroupOwnership'])->getMock();
            $ownership->expects(self::once())->method('deleteGroupOwnership')
                ->willReturnCallback(function (): void {
                    self::assertNull($this->placement('removed'), 'Expected a write before the simulated failure.');
                    throw new RuntimeException('Simulated cleanup failure');
                });
            $cleaner = $objectManager->create(TemplateStructureCleaner::class, ['ownershipResource' => $ownership]);
            try {
                $cleaner->removeTemplate(self::TEMPLATE);
                self::fail('Cleanup failure was swallowed.');
            } catch (RuntimeException $exception) {
                self::assertSame('Simulated cleanup failure', $exception->getMessage());
            }
            self::assertSame($before, $this->magentoStructure());
            self::assertNotEmpty($this->rows('ergonode_template_attribute_ownership', 'template_code', self::TEMPLATE));
            $importer->execute();
            self::assertNull($this->placement('removed'));
            self::assertNotNull($this->placement('manual'));
            self::assertNotNull($this->placement('required'));
        } finally {
            $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
            foreach ([
                'ergonode_template_attribute_ownership', 'ergonode_template_group_ownership',
                'ergonode_template_attribute', 'ergonode_template_section',
            ] as $table) {
                $resource->getConnection()->delete(
                    $resource->getTableName($table),
                    ['template_code = ?' => self::TEMPLATE]
                );
            }
            $resource->getConnection()->delete(
                $resource->getTableName('ergonode_template'),
                ['code = ?' => self::TEMPLATE]
            );
            $codes = array_map(fn (string $name): string => (string)$this->fixture($name)->getAttributeCode(), [
                'removed', 'manual', 'required', 'added',
            ]);
            $resource->getConnection()->delete(
                $resource->getTableName('ergonode_product_attribute_mapping'),
                ['magento_attribute_code IN (?)' => $codes]
            );
        }
    }

    public function testIncompleteSectionCannotRemovePlacementsOrMarkTemplateDeleted(): void
    {
        $importer = $this->prepare();
        $before = $this->magentoStructure();
        $this->incomplete = true;
        try {
            $importer->execute();
            self::fail('Incomplete section was accepted.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('incomplete', $exception->getMessage());
        }
        self::assertSame($before, $this->magentoStructure());
        self::assertSame(0, (int)$this->templateRow()['is_deleted']);
    }

    public function testSectionRenameReorderAndAttributeMovementUseExistingOwnedGroups(): void
    {
        $importer = $this->prepare();
        $detailsId = $this->placement('removed')['attribute_group_id'];
        $specificationId = $this->placement('required')['attribute_group_id'];
        $this->sections = ['specification' => ['required', 'removed'], 'details' => ['added', 'manual']];
        $this->specificationName = 'Renamed specification';
        $importer->execute();
        self::assertSame($specificationId, $this->placement('removed')['attribute_group_id']);
        self::assertSame($detailsId, $this->placement('added')['attribute_group_id']);
        $group = $this->rows('eav_attribute_group', 'attribute_group_id', $specificationId)[0];
        self::assertSame(1000, (int)$group['sort_order']);
        self::assertSame('Ergonode - Renamed specification', $group['attribute_group_name']);
        $after = $this->magentoStructure();
        $importer->execute();
        self::assertSame($after, $this->magentoStructure());
    }

    private function prepare(): TemplateListImporter
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert($resource->getTableName('ergonode_template'), [
            'code' => self::TEMPLATE, 'attribute_set_id' => $this->setId(),
            'raw_json' => '{}', 'content_hash' => hash('sha256', 'initial'),
        ]);
        foreach (['removed', 'manual', 'required', 'added'] as $name) {
            $connection->insert($resource->getTableName('ergonode_product_attribute_mapping'), [
                'ergonode_attribute_code' => $name,
                'magento_attribute_code' => $this->fixture($name)->getAttributeCode(),
                'ergonode_type' => 'TEXT', 'magento_type' => 'text', 'status' => 'complete',
                'content_hash' => hash('sha256', $name), 'sort_order' => 1,
            ]);
        }
        $importer = $this->importer();
        $importer->execute();
        Bootstrap::getObjectManager()->get(ManualPlacementResource::class)->save(
            $this->setId(),
            (int)$this->fixture('manual')->getAttributeId(),
            true
        );
        $connection->update($resource->getTableName('eav_attribute'), ['is_required' => 1], [
            'attribute_id = ?' => $this->fixture('required')->getAttributeId(),
        ]);
        return $importer;
    }

    private function importer(): TemplateListImporter
    {
        $objectManager = Bootstrap::getObjectManager();
        $client = $this->createStub(Client::class);
        $client->method('query')->willReturnCallback(
            fn (string $query, array $variables): array => $this->response($query, $variables)
        );
        $structure = $objectManager->create(StructureProcessor::class, [
            'templateStructureLoader' => $objectManager->create(TemplateStructureLoader::class, ['client' => $client]),
        ]);
        return $objectManager->create(TemplateListImporter::class, [
            'pageReader' => new TemplateListPageReader($client),
            'templateProcessor' => $objectManager->create(ImportedTemplateProcessor::class, [
                'templateLoader' => new TemplateLoader($client), 'contributors' => [$structure],
            ]),
        ]);
    }

    private function response(string $query, array $variables): array
    {
        if ($query === TemplateQueries::TEMPLATE_LIST) {
            return ['templateList' => $this->connection($this->deleted ? [] : [self::TEMPLATE])];
        }
        if ($query === TemplateStructureQueries::SECTION_ATTRIBUTES) {
            if ($this->paginateStructure && $variables['code'] === 'details') {
                ++$this->detailsPageCount;
                if ($variables['after'] === 'details-1' && $this->failSecondSectionPage) {
                    throw new LocalizedException(__('Simulated transport failure'));
                }
                $codes = $this->sections['details'];
                $isFirstPage = $variables['after'] === null;
                return ['section' => ['code' => 'details', 'attributeList' => $this->pageConnection(
                    array_slice($codes, $isFirstPage ? 0 : 1, 1),
                    $isFirstPage,
                    $isFirstPage ? 'details-1' : null
                )]];
            }
            return ['section' => ['code' => $variables['code'], 'attributeList' => $this->incomplete
                ? ['pageInfo' => ['hasNextPage' => false]]
                : $this->connection($this->sections[$variables['code']])]];
        }
        if ($query === TemplateStructureQueries::TEMPLATE_DETAILS && $this->paginateStructure) {
            ++$this->templatePageCount;
            $attributes = array_values(array_unique(array_merge(...array_values($this->sections))));
            $isFirstAttributePage = $variables['attributeAfter'] === null;
            $isFirstSectionPage = $variables['sectionAfter'] === null;
            $sections = $this->pageConnection(
                array_slice(array_keys($this->sections), $isFirstSectionPage ? 0 : 1, 1),
                $isFirstSectionPage,
                $isFirstSectionPage ? 'sections-1' : null
            );
            foreach ($sections['edges'] as &$edge) {
                $edge['node']['name'] = [['language' => 'en_US', 'value' => ucfirst($edge['node']['code'])]];
            }
            unset($edge);
            return ['template' => [
                'code' => self::TEMPLATE,
                'name' => [['language' => 'en_US', 'value' => 'Lifecycle']],
                'attributeList' => $this->pageConnection(
                    array_slice($attributes, $isFirstAttributePage ? 0 : 2, 2),
                    $isFirstAttributePage,
                    $isFirstAttributePage ? 'attributes-1' : null
                ),
                'sectionList' => $sections,
            ]];
        }
        $sections = $this->connection(array_keys($this->sections));
        foreach ($sections['edges'] as &$edge) {
            $label = $edge['node']['code'] === 'specification'
                ? $this->specificationName : ucfirst($edge['node']['code']);
            $edge['node']['name'] = [['language' => 'en_US', 'value' => $label]];
        }
        return ['template' => [
            'code' => self::TEMPLATE, 'name' => [['language' => 'en_US', 'value' => 'Lifecycle']],
            'sectionList' => $sections,
            'attributeList' => $this->connection(
                array_values(array_unique(array_merge(...array_values($this->sections))))
            ),
        ]];
    }

    private function connection(array $codes): array
    {
        return [
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            'edges' => array_map(static fn (string $code): array => ['node' => ['code' => $code]], $codes),
        ];
    }

    private function pageConnection(array $codes, bool $hasNextPage, ?string $endCursor): array
    {
        $connection = $this->connection($codes);
        $connection['pageInfo'] = ['hasNextPage' => $hasNextPage, 'endCursor' => $endCursor];
        return $connection;
    }

    private function magentoStructure(): array
    {
        $placements = $this->rows('eav_entity_attribute', 'attribute_set_id', $this->setId());
        $customIds = array_map(fn (string $name): int => (int)$this->fixture($name)->getAttributeId(), [
            'removed', 'manual', 'required', 'added',
        ]);
        return [
            'groups' => $this->rows('eav_attribute_group', 'attribute_set_id', $this->setId()),
            'placements' => $placements,
            'system' => array_values(array_filter(
                $placements,
                static fn (array $row): bool => !in_array((int)$row['attribute_id'], $customIds, true)
            )),
        ];
    }

    private function placement(string $name): ?array
    {
        foreach ($this->rows('eav_entity_attribute', 'attribute_set_id', $this->setId()) as $row) {
            if ((int)$row['attribute_id'] === (int)$this->fixture($name)->getAttributeId()) {
                return $row;
            }
        }
        return null;
    }

    private function templateRow(): array
    {
        return $this->rows('ergonode_template', 'code', self::TEMPLATE)[0];
    }

    private function rows(string $table, string $key, mixed $value): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        return $resource->getConnection()->fetchAll($resource->getConnection()->select()
            ->from($resource->getTableName($table))->where($key . ' = ?', $value)->order($key));
    }

    private function fixture(string $name): DataObject
    {
        return DataFixtureStorageManager::getStorage()->get($name);
    }

    private function setId(): int
    {
        return (int)$this->fixture('set')->getAttributeSetId();
    }
}
