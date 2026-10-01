<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttribute\Api\StructureProviderInterface;
use Ergonode\TemplateAttributeConsumer\Api\ManualPlacementSaverInterface;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateStructureSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetResource;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\AttributeSet as AttributeSetFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(AttributeSetFixture::class, ['attribute_set_name' => 'Ergonode old %uniqid%'], as: 'old_set'),
    DataFixture(AttributeSetFixture::class, ['attribute_set_name' => 'Ergonode new %uniqid%'], as: 'new_set'),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ergonode_owned_one_%uniqid%'], as: 'owned_one'),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ergonode_owned_two_%uniqid%'], as: 'owned_two'),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ergonode_manual_%uniqid%'], as: 'manual')
]
class TemplateReconciliationIntegrationTest extends TestCase
{
    public function testManualPlacementSurvivesSynchronizationAndRemovalFromTheTemplate(): void
    {
        $setId = (int)$this->fixture('old_set')->getAttributeSetId();
        $attribute = $this->fixture('owned_one');
        $attributeId = (int)$attribute->getAttributeId();
        $this->seedTemplate('manual-placement-template', $setId, [
            'details' => ['name' => 'Details', 'attribute' => $attribute, 'ergonode_code' => 'manual-material'],
        ]);
        $this->syncer()->syncTemplate('manual-placement-template', null, false, true);
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(ManualPlacementSaverInterface::class)
            ->save('manual-placement-template', $setId, $attributeId, true);
        $manual = $objectManager->get(ManualPlacementResource::class);
        self::assertTrue($manual->isManual($setId, $attributeId));
        self::assertFalse($manual->isManual((int)$this->fixture('new_set')->getAttributeSetId(), $attributeId));
        $nativeGroup = $this->resource()->insertAttributeGroup([
            'attribute_set_id' => $setId, 'attribute_group_name' => 'Advanced Inventory',
            'attribute_group_code' => 'manual_native_inventory', 'sort_order' => 999,
        ]);
        $placementTable = $this->table('eav_entity_attribute');
        $this->connection()->update(
            $placementTable,
            ['attribute_group_id' => $nativeGroup, 'sort_order' => 73],
            ['attribute_set_id = ?' => $setId, 'attribute_id = ?' => $attributeId]
        );
        $this->syncer()->syncTemplate('manual-placement-template', null, false, true);
        self::assertSame($nativeGroup, $this->attributeGroupId($setId, $attributeId));
        self::assertSame(73, (int)$this->connection()->fetchOne($this->connection()->select()
            ->from($placementTable, ['sort_order'])->where('attribute_set_id = ?', $setId)
            ->where('attribute_id = ?', $attributeId)));
        $this->connection()->delete(
            $this->table('ergonode_template_attribute'),
            ['template_code = ?' => 'manual-placement-template']
        );
        $this->connection()->delete(
            $this->table('ergonode_template_section'),
            ['template_code = ?' => 'manual-placement-template']
        );
        $this->syncer()->syncTemplate('manual-placement-template', null, false, true);
        $this->syncer()->syncTemplate('manual-placement-template', null, false, true);
        self::assertSame($nativeGroup, $this->attributeGroupId($setId, $attributeId));
        self::assertTrue($manual->isManual($setId, $attributeId));
        $structure = $objectManager->get(StructureProviderInterface::class)->get('manual-placement-template', $setId);
        $shown = array_column($structure['attributes'], null, 'attribute_id')[$attributeId];
        self::assertTrue($shown['manual_placement']);
        self::assertTrue($shown['missing_from_template']);
        self::assertSame(['manual-material'], $shown['ergonode_attribute_codes']);
        $objectManager->get(ManualPlacementSaverInterface::class)
            ->save('manual-placement-template', $setId, $attributeId, false);
        self::assertFalse($manual->isManual($setId, $attributeId));
    }

    public function testCleanupRemovesOwnedStructureAndPreservesManualAttributeIdempotently(): void
    {
        $attributeSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $ownedOne = $this->fixture('owned_one');
        $ownedTwo = $this->fixture('owned_two');
        $manual = $this->fixture('manual');
        $this->seedTemplate('cleanup-template', $attributeSetId, [
            'details' => ['name' => 'Details', 'attribute' => $ownedOne, 'ergonode_code' => 'owned-one'],
            'specification' => [
                'name' => 'Specification',
                'attribute' => $ownedTwo,
                'ergonode_code' => 'owned-two',
            ],
        ]);
        $syncer = $this->syncer();
        $syncer->syncTemplate('cleanup-template', null, false, true);

        $detailsGroupId = $this->sectionGroupId('cleanup-template', 'details');
        $specificationGroupId = $this->sectionGroupId('cleanup-template', 'specification');
        $this->resource()->insertEntityAttribute(
            $this->productEntityTypeId(),
            $attributeSetId,
            $specificationGroupId,
            (int)$manual->getAttributeId(),
            90
        );
        $connection = $this->connection();
        $connection->delete(
            $this->table('ergonode_template_attribute'),
            ['template_code = ?' => 'cleanup-template']
        );
        $connection->delete(
            $this->table('ergonode_template_section'),
            ['template_code = ?' => 'cleanup-template', 'section_code = ?' => 'specification']
        );

        $stats = $syncer->syncTemplate('cleanup-template', null, false, true);

        self::assertSame(2, $stats['attributes_removed']);
        self::assertSame(1, $stats['groups_deleted']);
        self::assertFalse($this->groupExists($detailsGroupId));
        self::assertTrue($this->groupExists($specificationGroupId));
        self::assertSame(
            $specificationGroupId,
            $this->attributeGroupId($attributeSetId, (int)$manual->getAttributeId())
        );
        self::assertSame(0, $this->attributeGroupId($attributeSetId, (int)$ownedOne->getAttributeId()));
        self::assertSame(0, $this->attributeGroupId($attributeSetId, (int)$ownedTwo->getAttributeId()));
        self::assertSame(0, $this->ownershipCount('ergonode_template_group_ownership'));
        self::assertSame(0, $this->ownershipCount('ergonode_template_attribute_ownership'));

        $secondStats = $syncer->syncTemplate('cleanup-template', null, false, true);

        self::assertSame(0, $secondStats['attributes_removed']);
        self::assertSame(0, $secondStats['groups_deleted']);
        self::assertTrue($this->groupExists($specificationGroupId));
    }

    public function testSuccessfulSetRemapCleansPreviousSetAfterCreatingNewStructure(): void
    {
        $oldSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $newSetId = (int)$this->fixture('new_set')->getAttributeSetId();
        $attribute = $this->fixture('owned_one');
        $attributeId = (int)$attribute->getAttributeId();
        $this->seedTemplate('remap-template', $oldSetId, [
            'details' => ['name' => 'Details', 'attribute' => $attribute, 'ergonode_code' => 'remap-owned'],
        ]);
        $syncer = $this->syncer();
        $syncer->syncTemplate('remap-template', null, false, true);
        $oldGroupId = $this->sectionGroupId('remap-template', 'details');

        $stats = $syncer->syncTemplate('remap-template', $newSetId, false, true);
        $newGroupId = $this->sectionGroupId('remap-template', 'details');

        self::assertSame(1, $stats['attributes_removed']);
        self::assertSame(1, $stats['groups_deleted']);
        self::assertFalse($this->groupExists($oldGroupId));
        self::assertNotSame($oldGroupId, $newGroupId);
        self::assertSame(0, $this->attributeGroupId($oldSetId, $attributeId));
        self::assertSame($newGroupId, $this->attributeGroupId($newSetId, $attributeId));
        self::assertSame($newSetId, $this->templateAttributeSetId('remap-template'));
        self::assertSame(1, $this->ownershipCount('ergonode_template_group_ownership'));
        self::assertSame(1, $this->ownershipCount('ergonode_template_attribute_ownership'));
    }

    public function testSyncReusesGroupFromRequestedSetAfterRemapWithoutCleanup(): void
    {
        $oldSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $newSetId = (int)$this->fixture('new_set')->getAttributeSetId();
        $attribute = $this->fixture('owned_one');
        $this->seedTemplate('remap-back-template', $oldSetId, [
            'details' => ['name' => 'Details', 'attribute' => $attribute, 'ergonode_code' => 'remap-back'],
        ]);
        $syncer = $this->syncer();
        $syncer->syncTemplate('remap-back-template', null, false, false);
        $oldGroupId = $this->sectionGroupId('remap-back-template', 'details');

        $syncer->syncTemplate('remap-back-template', $newSetId, false, false);
        $newGroupId = $this->sectionGroupId('remap-back-template', 'details');
        self::assertNotSame($oldGroupId, $newGroupId);

        $changeReport = Bootstrap::getObjectManager()->get(ChangeReport::class);
        $changeReport->reset();
        $syncStats = $syncer->syncTemplate('remap-back-template', $oldSetId, false, false);
        $syncEntry = $this->reportEntry(
            $changeReport->getEntries(true),
            'template_group',
            'remap-back-template::details'
        );

        self::assertSame(ChangeReport::ACTION_UNCHANGED, $syncEntry['action']);
        self::assertSame('ergonode_details', $syncEntry['details']['attribute_group_code']);
        self::assertSame(0, $syncStats['groups_created']);
        self::assertSame(0, $syncStats['groups_updated']);
        self::assertSame($oldGroupId, $this->sectionGroupId('remap-back-template', 'details'));
    }

    public function testSyncAccountsForAttributeMovedOutOfObsoleteGroup(): void
    {
        $attributeSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $attribute = $this->fixture('owned_one');
        $this->seedTemplate('move-template', $attributeSetId, [
            'details' => ['name' => 'Details', 'attribute' => $attribute, 'ergonode_code' => 'moved'],
        ]);
        $syncer = $this->syncer();
        $syncer->syncTemplate('move-template', null, false, true);
        $obsoleteGroupId = $this->sectionGroupId('move-template', 'details');
        $this->replaceTemplateSection('move-template', 'details', 'specification', 'Specification');

        $changeReport = Bootstrap::getObjectManager()->get(ChangeReport::class);
        $changeReport->reset();
        $syncStats = $syncer->syncTemplate('move-template', null, false, true);
        $syncGroupEntry = $this->reportEntry(
            $changeReport->getEntries(true),
            'template_group',
            'move-template::obsolete::details'
        );

        self::assertSame(1, $syncStats['attributes_moved']);
        self::assertSame(1, $syncStats['groups_deleted']);
        self::assertSame(ChangeReport::ACTION_UPDATED, $syncGroupEntry['action']);
        self::assertStringContainsString('Deleted obsolete empty', $syncGroupEntry['message']);
        self::assertFalse($this->groupExists($obsoleteGroupId));
    }

    public function testCollidingSectionCodesUseDistinctStableGroups(): void
    {
        $attributeSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $ownedOne = $this->fixture('owned_one');
        $ownedTwo = $this->fixture('owned_two');
        $this->seedTemplate('collision-template', $attributeSetId, [
            'foo-bar' => ['name' => 'Foo hyphen', 'attribute' => $ownedOne, 'ergonode_code' => 'collision-one'],
            'foo_bar' => ['name' => 'Foo underscore', 'attribute' => $ownedTwo, 'ergonode_code' => 'collision-two'],
        ]);

        $changeReport = Bootstrap::getObjectManager()->get(ChangeReport::class);
        $changeReport->reset();
        $syncStats = $this->syncer()->syncTemplate('collision-template', null, false, true);
        $syncEntries = $changeReport->getEntries(true);
        $syncHyphenGroup = $this->reportEntry(
            $syncEntries,
            'template_group',
            'collision-template::foo-bar'
        );
        $syncUnderscoreGroup = $this->reportEntry(
            $syncEntries,
            'template_group',
            'collision-template::foo_bar'
        );
        $hyphenGroupId = $this->sectionGroupId('collision-template', 'foo-bar');
        $underscoreGroupId = $this->sectionGroupId('collision-template', 'foo_bar');
        $hyphenGroupCode = $this->attributeGroupCode($hyphenGroupId);
        $underscoreGroupCode = $this->attributeGroupCode($underscoreGroupId);

        self::assertSame(2, $syncStats['groups_created']);
        self::assertNotSame($hyphenGroupId, $underscoreGroupId);
        self::assertSame('ergonode_foo_bar', $hyphenGroupCode);
        self::assertSame(
            'ergonode_foo_bar_' . substr(hash('sha256', 'foo_bar'), 0, 8),
            $underscoreGroupCode
        );
        self::assertSame($hyphenGroupCode, $syncHyphenGroup['details']['attribute_group_code']);
        self::assertSame($underscoreGroupCode, $syncUnderscoreGroup['details']['attribute_group_code']);
        self::assertSame(
            $hyphenGroupId,
            $this->attributeGroupId($attributeSetId, (int)$ownedOne->getAttributeId())
        );
        self::assertSame(
            $underscoreGroupId,
            $this->attributeGroupId($attributeSetId, (int)$ownedTwo->getAttributeId())
        );

        $secondStats = $this->syncer()->syncTemplate('collision-template', null, false, true);

        self::assertSame(0, $secondStats['groups_created']);
        self::assertSame(0, $secondStats['groups_updated']);
        self::assertSame($hyphenGroupId, $this->sectionGroupId('collision-template', 'foo-bar'));
        self::assertSame($underscoreGroupId, $this->sectionGroupId('collision-template', 'foo_bar'));
        self::assertSame($hyphenGroupCode, $this->attributeGroupCode($hyphenGroupId));
        self::assertSame($underscoreGroupCode, $this->attributeGroupCode($underscoreGroupId));
    }

    public function testHistoricalSharedGroupMappingIsSplitAndRepairedIdempotently(): void
    {
        $attributeSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $ownedOne = $this->fixture('owned_one');
        $ownedTwo = $this->fixture('owned_two');
        $this->seedTemplate('shared-group-template', $attributeSetId, [
            'foo-bar' => ['name' => 'Foo hyphen', 'attribute' => $ownedOne, 'ergonode_code' => 'shared-one'],
            'foo_bar' => ['name' => 'Foo underscore', 'attribute' => $ownedTwo, 'ergonode_code' => 'shared-two'],
        ]);
        $sharedGroupId = $this->resource()->insertAttributeGroup([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_name' => 'Ergonode - Foo hyphen',
            'sort_order' => 10,
            'attribute_group_code' => 'ergonode_foo_bar',
            'tab_group_code' => 'Ergonode - Foo hyphen',
        ]);
        $this->connection()->update(
            $this->table('ergonode_template_section'),
            ['attribute_group_id' => $sharedGroupId],
            ['template_code = ?' => 'shared-group-template']
        );
        $firstEntityAttributeId = $this->resource()->insertEntityAttribute(
            $this->productEntityTypeId(),
            $attributeSetId,
            $sharedGroupId,
            (int)$ownedOne->getAttributeId(),
            1
        );
        $secondEntityAttributeId = $this->resource()->insertEntityAttribute(
            $this->productEntityTypeId(),
            $attributeSetId,
            $sharedGroupId,
            (int)$ownedTwo->getAttributeId(),
            1
        );
        $ownershipResource = $this->ownershipResource();
        $ownershipResource->saveGroupOwnership(
            'shared-group-template',
            $attributeSetId,
            'foo-bar',
            $sharedGroupId
        );
        $ownershipResource->saveAttributeOwnership(
            'shared-group-template',
            $attributeSetId,
            'shared-one',
            (int)$ownedOne->getAttributeId(),
            $sharedGroupId,
            $firstEntityAttributeId
        );
        $ownershipResource->saveAttributeOwnership(
            'shared-group-template',
            $attributeSetId,
            'shared-two',
            (int)$ownedTwo->getAttributeId(),
            $sharedGroupId,
            $secondEntityAttributeId
        );

        $changeReport = Bootstrap::getObjectManager()->get(ChangeReport::class);
        $changeReport->reset();
        $syncStats = $this->syncer()->syncTemplate('shared-group-template', null, false, true);
        $syncEntry = $this->reportEntry(
            $changeReport->getEntries(true),
            'template_group',
            'shared-group-template::foo_bar'
        );
        $hyphenGroupId = $this->sectionGroupId('shared-group-template', 'foo-bar');
        $underscoreGroupId = $this->sectionGroupId('shared-group-template', 'foo_bar');
        $hashedCode = 'ergonode_foo_bar_' . substr(hash('sha256', 'foo_bar'), 0, 8);

        self::assertSame(1, $syncStats['groups_created']);
        self::assertSame(ChangeReport::ACTION_INSERTED, $syncEntry['action']);
        self::assertSame($hashedCode, $syncEntry['details']['attribute_group_code']);
        self::assertSame($sharedGroupId, $hyphenGroupId);
        self::assertNotSame($sharedGroupId, $underscoreGroupId);
        self::assertSame('ergonode_foo_bar', $this->attributeGroupCode($hyphenGroupId));
        self::assertSame($hashedCode, $this->attributeGroupCode($underscoreGroupId));
        self::assertSame(
            $hyphenGroupId,
            $this->attributeGroupId($attributeSetId, (int)$ownedOne->getAttributeId())
        );
        self::assertSame(
            $underscoreGroupId,
            $this->attributeGroupId($attributeSetId, (int)$ownedTwo->getAttributeId())
        );

        $secondStats = $this->syncer()->syncTemplate('shared-group-template', null, false, true);

        self::assertSame(0, $secondStats['groups_created']);
        self::assertSame(0, $secondStats['groups_updated']);
        self::assertSame(0, $secondStats['attributes_moved']);
        self::assertSame($hyphenGroupId, $this->sectionGroupId('shared-group-template', 'foo-bar'));
        self::assertSame($underscoreGroupId, $this->sectionGroupId('shared-group-template', 'foo_bar'));
    }

    public function testSyncReusesCodeReleasedByEarlierMappedGroupUpdate(): void
    {
        $attributeSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $ownedOne = $this->fixture('owned_one');
        $ownedTwo = $this->fixture('owned_two');
        $this->seedTemplate('released-code-template', $attributeSetId, [
            'moving' => ['name' => 'Moving', 'attribute' => $ownedOne, 'ergonode_code' => 'released-code-one'],
        ]);
        $syncer = $this->syncer();
        $syncer->syncTemplate('released-code-template', null, false, true);
        $movingGroupId = $this->sectionGroupId('released-code-template', 'moving');
        $this->resource()->updateAttributeGroup($movingGroupId, [
            'attribute_group_code' => 'ergonode_foo_bar',
        ]);
        $this->appendTemplateSection(
            'released-code-template',
            'foo_bar',
            'Foo underscore',
            $ownedTwo,
            'released-code-two',
            20
        );

        $changeReport = Bootstrap::getObjectManager()->get(ChangeReport::class);
        $changeReport->reset();
        $syncer->syncTemplate('released-code-template', null, false, true);
        $syncEntry = $this->reportEntry(
            $changeReport->getEntries(true),
            'template_group',
            'released-code-template::foo_bar'
        );
        $newGroupId = $this->sectionGroupId('released-code-template', 'foo_bar');

        self::assertSame(ChangeReport::ACTION_INSERTED, $syncEntry['action']);
        self::assertSame('ergonode_foo_bar', $syncEntry['details']['attribute_group_code']);
        self::assertNotSame($movingGroupId, $newGroupId);
        self::assertSame('ergonode_moving', $this->attributeGroupCode($movingGroupId));
        self::assertSame('ergonode_foo_bar', $this->attributeGroupCode($newGroupId));
        self::assertSame(
            $newGroupId,
            $this->attributeGroupId($attributeSetId, (int)$ownedTwo->getAttributeId())
        );
    }

    #[Config('ergonode_templates/import/create_attribute_sets', '1')]
    public function testNewSetSyncReservesCodesClonedFromDefaultSkeleton(): void
    {
        $defaultAttributeSetId = Bootstrap::getObjectManager()
            ->get(AttributeSetResource::class)
            ->getDefaultProductAttributeSetId();
        $baseCode = 'ergonode_skeleton_collision';
        $this->resource()->insertAttributeGroup([
            'attribute_set_id' => $defaultAttributeSetId,
            'attribute_group_name' => 'Foreign skeleton group',
            'sort_order' => 900,
            'attribute_group_code' => $baseCode,
            'tab_group_code' => 'Foreign skeleton group',
        ]);
        $attribute = $this->fixture('owned_one');
        $this->seedTemplate('skeleton-template', null, [
            'skeleton-collision' => [
                'name' => 'Skeleton collision',
                'attribute' => $attribute,
                'ergonode_code' => 'skeleton-owned',
            ],
        ]);

        $changeReport = Bootstrap::getObjectManager()->get(ChangeReport::class);
        $changeReport->reset();
        $syncStats = $this->syncer()->syncTemplate('skeleton-template', null, true, true);
        $syncEntry = $this->reportEntry(
            $changeReport->getEntries(true),
            'template_group',
            'skeleton-template::skeleton-collision'
        );
        $newAttributeSetId = $this->templateAttributeSetId('skeleton-template');
        $managedGroupId = $this->sectionGroupId('skeleton-template', 'skeleton-collision');
        $clonedGroupId = $this->attributeGroupIdByCode($newAttributeSetId, $baseCode);
        $hashedCode = $baseCode . '_' . substr(hash('sha256', 'skeleton-collision'), 0, 8);

        self::assertSame(1, $syncStats['attribute_sets_created']);
        self::assertSame(1, $syncStats['groups_created']);
        self::assertSame(ChangeReport::ACTION_INSERTED, $syncEntry['action']);
        self::assertSame($hashedCode, $syncEntry['details']['attribute_group_code']);
        self::assertSame($hashedCode, $this->attributeGroupCode($managedGroupId));
        self::assertGreaterThan(0, $clonedGroupId);
        self::assertNotSame($clonedGroupId, $managedGroupId);
    }

    public function testSyncDoesNotReleaseCodeOfUnownedMappedGroup(): void
    {
        $attributeSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $ownedOne = $this->fixture('owned_one');
        $ownedTwo = $this->fixture('owned_two');
        $this->seedTemplate('unowned-code-template', $attributeSetId, [
            'moving' => ['name' => 'Moving', 'attribute' => $ownedOne, 'ergonode_code' => 'unowned-code-one'],
            'foo_bar' => ['name' => 'Foo underscore', 'attribute' => $ownedTwo, 'ergonode_code' => 'unowned-code-two'],
        ]);
        $manualGroupId = $this->resource()->insertAttributeGroup([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_name' => 'Manual group',
            'sort_order' => 10,
            'attribute_group_code' => 'ergonode_foo_bar',
            'tab_group_code' => 'Manual group',
        ]);
        $this->connection()->update(
            $this->table('ergonode_template_section'),
            ['attribute_group_id' => $manualGroupId],
            ['template_code = ?' => 'unowned-code-template', 'section_code = ?' => 'moving']
        );

        $changeReport = Bootstrap::getObjectManager()->get(ChangeReport::class);
        $changeReport->reset();
        $this->syncer()->syncTemplate('unowned-code-template', null, false, true);
        $syncEntry = $this->reportEntry(
            $changeReport->getEntries(true),
            'template_group',
            'unowned-code-template::foo_bar'
        );
        $hashedCode = 'ergonode_foo_bar_' . substr(hash('sha256', 'foo_bar'), 0, 8);
        $newGroupId = $this->sectionGroupId('unowned-code-template', 'foo_bar');

        self::assertSame(ChangeReport::ACTION_INSERTED, $syncEntry['action']);
        self::assertSame($hashedCode, $syncEntry['details']['attribute_group_code']);
        self::assertSame('ergonode_foo_bar', $this->attributeGroupCode($manualGroupId));
        self::assertNotSame($manualGroupId, $this->sectionGroupId('unowned-code-template', 'moving'));
        $native = $this->resource()->findAttributeGroupById($attributeSetId, $manualGroupId);
        self::assertSame('Manual group', $native['attribute_group_name']);
        self::assertSame(10, (int)$native['sort_order']);
        self::assertSame($hashedCode, $this->attributeGroupCode($newGroupId));
    }

    public function testFailedSetRemapLeavesPreviousOwnedStructureUntouched(): void
    {
        $oldSetId = (int)$this->fixture('old_set')->getAttributeSetId();
        $attribute = $this->fixture('owned_one');
        $attributeId = (int)$attribute->getAttributeId();
        $this->seedTemplate('failed-remap-template', $oldSetId, [
            'details' => ['name' => 'Details', 'attribute' => $attribute, 'ergonode_code' => 'failed-owned'],
        ]);
        $syncer = $this->syncer();
        $syncer->syncTemplate('failed-remap-template', null, false, true);
        $oldGroupId = $this->sectionGroupId('failed-remap-template', 'details');

        try {
            $syncer->syncTemplate('failed-remap-template', PHP_INT_MAX, false, true);
            self::fail('Expected invalid target attribute set to fail.');
        } catch (LocalizedException) {
            self::assertTrue($this->groupExists($oldGroupId));
            self::assertSame($oldGroupId, $this->attributeGroupId($oldSetId, $attributeId));
            self::assertSame($oldSetId, $this->templateAttributeSetId('failed-remap-template'));
            self::assertSame(1, $this->ownershipCount('ergonode_template_group_ownership'));
            self::assertSame(1, $this->ownershipCount('ergonode_template_attribute_ownership'));
        }
    }

    /**
     * @param array<string, array{name: string, attribute: DataObject, ergonode_code: string}> $sections
     */
    private function seedTemplate(string $templateCode, ?int $attributeSetId, array $sections): void
    {
        $connection = $this->connection();
        $hash = hash('sha256', $templateCode);
        $connection->insert($this->table('ergonode_template'), [
            'code' => $templateCode,
            'attribute_set_id' => $attributeSetId,
            'content_hash' => $hash,
            'raw_json' => '{}',
        ]);
        $sortOrder = 10;
        foreach ($sections as $sectionCode => $section) {
            $this->appendTemplateSection(
                $templateCode,
                $sectionCode,
                $section['name'],
                $section['attribute'],
                $section['ergonode_code'],
                $sortOrder
            );
            $sortOrder += 10;
        }
    }

    private function appendTemplateSection(
        string $templateCode,
        string $sectionCode,
        string $sectionName,
        DataObject $attribute,
        string $ergonodeCode,
        int $sortOrder
    ): void {
        $hash = hash('sha256', $templateCode);
        $connection = $this->connection();
        $connection->insert($this->table('ergonode_template_section'), [
            'template_code' => $templateCode,
            'section_code' => $sectionCode,
            'attribute_group_id' => null,
            'sort_order' => $sortOrder,
            'content_hash' => $hash,
            'raw_json' => json_encode([
                'name' => [['language' => 'en_US', 'value' => $sectionName]],
            ], JSON_THROW_ON_ERROR),
        ]);
        $connection->insert($this->table('ergonode_template_attribute'), [
            'template_code' => $templateCode,
            'section_code' => $sectionCode,
            'attribute_code' => $ergonodeCode,
            'sort_order' => 1,
        ]);
        $connection->insert($this->table('ergonode_product_attribute_mapping'), [
            'ergonode_attribute_code' => $ergonodeCode,
            'magento_attribute_code' => (string)$attribute->getAttributeCode(),
            'ergonode_type' => 'TEXT',
            'magento_type' => 'text',
            'status' => 'complete',
            'content_hash' => $hash,
            'sort_order' => 1,
        ]);
    }

    private function replaceTemplateSection(
        string $templateCode,
        string $oldSectionCode,
        string $newSectionCode,
        string $newSectionName
    ): void {
        $this->connection()->update(
            $this->table('ergonode_template_section'),
            [
                'section_code' => $newSectionCode,
                'attribute_group_id' => null,
                'raw_json' => json_encode([
                    'name' => [['language' => 'en_US', 'value' => $newSectionName]],
                ], JSON_THROW_ON_ERROR),
            ],
            ['template_code = ?' => $templateCode, 'section_code = ?' => $oldSectionCode]
        );
        $this->connection()->update(
            $this->table('ergonode_template_attribute'),
            ['section_code' => $newSectionCode],
            ['template_code = ?' => $templateCode, 'section_code = ?' => $oldSectionCode]
        );
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
    private function reportEntry(array $entries, string $entity, string $identifier): array
    {
        foreach ($entries as $entry) {
            if ($entry['entity'] === $entity && $entry['identifier'] === $identifier) {
                return $entry;
            }
        }

        self::fail(sprintf('Report entry %s:%s was not found.', $entity, $identifier));
    }

    private function syncer(): MagentoTemplateStructureSyncer
    {
        return Bootstrap::getObjectManager()->get(MagentoTemplateStructureSyncer::class);
    }

    private function resource(): TemplateStructureResource
    {
        return Bootstrap::getObjectManager()->get(TemplateStructureResource::class);
    }

    private function ownershipResource(): TemplateStructureOwnershipResource
    {
        return Bootstrap::getObjectManager()->get(TemplateStructureOwnershipResource::class);
    }

    private function connection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function table(string $name): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getTableName($name);
    }

    private function productEntityTypeId(): int
    {
        return Bootstrap::getObjectManager()->get(AttributeSetManager::class)->getProductEntityTypeId();
    }

    private function sectionGroupId(string $templateCode, string $sectionCode): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('ergonode_template_section'), ['attribute_group_id'])
                ->where('template_code = ?', $templateCode)
                ->where('section_code = ?', $sectionCode)
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

    private function attributeGroupCode(int $attributeGroupId): string
    {
        return (string)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('eav_attribute_group'), ['attribute_group_code'])
                ->where('attribute_group_id = ?', $attributeGroupId)
        );
    }

    private function attributeGroupIdByCode(int $attributeSetId, string $attributeGroupCode): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('eav_attribute_group'), ['attribute_group_id'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_group_code = ?', $attributeGroupCode)
        );
    }

    private function templateAttributeSetId(string $templateCode): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('ergonode_template'), ['attribute_set_id'])
                ->where('code = ?', $templateCode)
        );
    }

    private function groupExists(int $attributeGroupId): bool
    {
        return (bool)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table('eav_attribute_group'), ['COUNT(*)'])
                ->where('attribute_group_id = ?', $attributeGroupId)
        );
    }

    private function ownershipCount(string $table): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()->select()->from($this->table($table), ['COUNT(*)'])
        );
    }

    private function fixture(string $alias): DataObject
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($alias);
        self::assertNotNull($fixture);

        return $fixture;
    }
}
