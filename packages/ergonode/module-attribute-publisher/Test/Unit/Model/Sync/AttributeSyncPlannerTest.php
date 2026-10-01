<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\AttributePublisher\Model\Sync\AttributeSyncPlan;
use Ergonode\AttributePublisher\Model\Sync\AttributeSyncPlanner;
use PHPUnit\Framework\TestCase;

class AttributeSyncPlannerTest extends TestCase
{
    public function testEachNameSetUsesItsOwnRequestedLanguages(): void
    {
        $planner = new AttributeSyncPlanner(new AttributeMutationFactory());
        $desired = new AttributeState('color', 'select', 'LOCAL', ['en_US' => 'Color'], [], [], [
            new AttributeOptionState('red', ['pl_PL' => 'Czerwony']),
            new AttributeOptionState('blue', ['de_DE' => 'Blau']),
        ]);
        $remote = new AttributeState('color', 'select', 'LOCAL', ['en_US' => 'Color', 'pl_PL' => 'Kolor'], [], [], [
            new AttributeOptionState('red', ['en_US' => 'Red', 'pl_PL' => 'Czerwony']),
            new AttributeOptionState('blue', ['de_DE' => 'Blau', 'pl_PL' => 'Niebieski']),
        ]);
        foreach (['update', 'create_only'] as $mode) {
            self::assertSame(['en_US', 'pl_PL', 'de_DE'], $planner->getReadLanguages($desired, $mode));
            self::assertSame(AttributeSyncPlan::STATUS_NOOP, $planner->plan($desired, $remote, $mode)->getStatus());
        }
        self::assertSame([], $planner->getReadLanguages($desired, 'reconcile'));
        self::assertSame(['attributeSetName'], $this->fields($planner->plan($desired, $remote, 'reconcile')));
    }

    public function testNumericOptionCodesAreDeletedAsStringsOnlyDuringReconcile(): void
    {
        $planner = new AttributeSyncPlanner(new AttributeMutationFactory());
        $desired = new AttributeState('size', 'select', 'LOCAL');
        $remote = new AttributeState('size', 'select', 'LOCAL', [], [], [], [
            new AttributeOptionState('0', []), new AttributeOptionState('123', []), new AttributeOptionState('old', []),
        ]);
        $plan = $planner->plan($desired, $remote, AttributeSynchronizerInterface::MODE_RECONCILE);
        self::assertSame(['0', '123', 'old'], array_map(
            static fn ($operation): string => $operation->getVariables()['input']->getValue()['optionCode'],
            $plan->getStages()[0]
        ));
        foreach ([
            AttributeSynchronizerInterface::MODE_UPDATE,
            AttributeSynchronizerInterface::MODE_CREATE_ONLY,
        ] as $mode) {
            self::assertSame(AttributeSyncPlan::STATUS_NOOP, $planner->plan($desired, $remote, $mode)->getStatus());
        }
    }

    public function testCreateThenIdenticalStateIsNoop(): void
    {
        $state = new AttributeState('material', 'text', 'GLOBAL', ['pl_PL' => 'Materiał'], ['unique' => false]);
        $planner = new AttributeSyncPlanner(new AttributeMutationFactory());

        $plan = $planner->plan($state, null, AttributeSynchronizerInterface::MODE_UPDATE);
        self::assertSame('attributeCreateText', $plan->getStages()[0][0]->getField());
        self::assertSame(AttributeSyncPlan::STATUS_NOOP, $planner->plan(
            $state,
            $state,
            AttributeSynchronizerInterface::MODE_UPDATE
        )->getStatus());
    }

    public function testUnsupportedAndImmutableChangesAreExplicit(): void
    {
        $planner = new AttributeSyncPlanner(new AttributeMutationFactory());
        $unsupported = new AttributeState('x', 'unknown', 'LOCAL');
        self::assertSame(AttributeSyncPlan::STATUS_UNSUPPORTED, $planner->plan(
            $unsupported,
            null,
            AttributeSynchronizerInterface::MODE_UPDATE
        )->getStatus());

        $desired = new AttributeState('x', 'text', 'GLOBAL', [], ['unique' => false]);
        $remote = new AttributeState('x', 'text', 'LOCAL', [], ['unique' => false]);
        self::assertSame(AttributeSyncPlan::STATUS_CONFLICT, $planner->plan(
            $desired,
            $remote,
            AttributeSynchronizerInterface::MODE_UPDATE
        )->getStatus());

        $desired = new AttributeState('x', 'text', 'LOCAL', [], ['unique' => true]);
        $remote = new AttributeState('x', 'text', 'LOCAL', [], ['unique' => false]);
        self::assertSame(AttributeSyncPlan::STATUS_UNSUPPORTED, $planner->plan(
            $desired,
            $remote,
            AttributeSynchronizerInterface::MODE_UPDATE
        )->getStatus());
    }

    public function testOptionsAreAddedAndOnlyRemovedInReconcileMode(): void
    {
        $planner = new AttributeSyncPlanner(new AttributeMutationFactory());
        $desired = new AttributeState('color', 'select', 'LOCAL', [], [], [], [
            new AttributeOptionState('red', ['pl_PL' => 'Czerwony']),
        ]);
        $remote = new AttributeState('color', 'select', 'LOCAL', [], [], [], [
            new AttributeOptionState('blue', ['pl_PL' => 'Niebieski']),
        ]);

        $updateFields = $this->fields($planner->plan($desired, $remote, AttributeSynchronizerInterface::MODE_UPDATE));
        $afterAdd = new AttributeState('color', 'select', 'LOCAL', [], [], [], [
            new AttributeOptionState('blue', ['pl_PL' => 'Niebieski']),
            new AttributeOptionState('red', ['pl_PL' => 'Czerwony']),
        ]);
        $reconcileFields = $this->fields($planner->plan(
            $desired,
            $afterAdd,
            AttributeSynchronizerInterface::MODE_RECONCILE
        ));
        self::assertContains('attributeSelectAddOption', $updateFields);
        self::assertNotContains('attributeSelectDeleteOption', $updateFields);
        self::assertContains('attributeSelectDeleteOption', $reconcileFields);
    }

    public function testUnreadableParameterIsUnsupportedInEveryMode(): void
    {
        $planner = new AttributeSyncPlanner(new AttributeMutationFactory());
        $desired = new AttributeState('weight', 'unit', 'LOCAL', [], ['unitName' => 'KILOGRAM']);
        $remote = new AttributeState('weight', 'unit', 'LOCAL');

        self::assertSame(AttributeSyncPlan::STATUS_UNSUPPORTED, $planner->plan(
            $desired,
            $remote,
            AttributeSynchronizerInterface::MODE_UPDATE
        )->getStatus());
        self::assertSame(AttributeSyncPlan::STATUS_UNSUPPORTED, $planner->plan(
            $desired,
            $remote,
            AttributeSynchronizerInterface::MODE_CREATE_ONLY
        )->getStatus());
    }

    public function testCreateOnlyResumesNonDestructiveStagesForCompatibleExistingAttribute(): void
    {
        $desired = new AttributeState(
            'material',
            'text',
            'LOCAL',
            ['pl_PL' => 'Materiał'],
            ['unique' => false],
            ['source' => 'magento']
        );
        $remote = new AttributeState('material', 'text', 'LOCAL', ['pl_PL' => 'Material'], ['unique' => false]);

        $plan = (new AttributeSyncPlanner(new AttributeMutationFactory()))->plan(
            $desired,
            $remote,
            AttributeSynchronizerInterface::MODE_CREATE_ONLY
        );

        self::assertSame(AttributeSyncPlan::STATUS_READY, $plan->getStatus());
        self::assertSame(['attributeSetName', 'attributeAddMetadata'], $this->fields($plan));
        self::assertCount(1, $plan->getStages());
    }

    public function testOptionReplacementOrderingIsRestrictedToReconcileMode(): void
    {
        $desired = new AttributeState('color', 'select', 'LOCAL', [], [], [], [
            new AttributeOptionState('green', []),
            new AttributeOptionState('red', []),
        ]);
        $remote = new AttributeState('color', 'select', 'LOCAL', [], [], [], [
            new AttributeOptionState('blue', []),
            new AttributeOptionState('red', []),
            new AttributeOptionState('green', []),
        ]);

        $plan = (new AttributeSyncPlanner(new AttributeMutationFactory()))->plan(
            $desired,
            $remote,
            AttributeSynchronizerInterface::MODE_UPDATE
        );
        self::assertSame(AttributeSyncPlan::STATUS_NOOP, $plan->getStatus());

        $reconcileDelete = (new AttributeSyncPlanner(new AttributeMutationFactory()))->plan(
            $desired,
            $remote,
            AttributeSynchronizerInterface::MODE_RECONCILE
        );
        self::assertSame('attributeSelectDeleteOption', $reconcileDelete->getStages()[0][0]->getField());

        $remoteWithoutExtra = new AttributeState('color', 'select', 'LOCAL', [], [], [], [
            new AttributeOptionState('red', []),
            new AttributeOptionState('green', []),
        ]);
        $reconcileOrder = (new AttributeSyncPlanner(new AttributeMutationFactory()))->plan(
            $desired,
            $remoteWithoutExtra,
            AttributeSynchronizerInterface::MODE_RECONCILE
        );
        $operation = $reconcileOrder->getStages()[0][0];
        $input = $operation->getVariables()['input']->getValue();

        self::assertSame('attributeSelectSetOptions', $operation->getField());
        self::assertSame(['green', 'red'], array_column($input['options'], 'code'));
    }

    public function testTargetSpecificRequiredCreateParameterIsNeverGuessed(): void
    {
        $plan = (new AttributeSyncPlanner(new AttributeMutationFactory()))->plan(
            new AttributeState('weight', 'unit', 'LOCAL'),
            null,
            AttributeSynchronizerInterface::MODE_UPDATE
        );

        self::assertSame(AttributeSyncPlan::STATUS_UNSUPPORTED, $plan->getStatus());
        self::assertStringContainsString('unitName', $plan->getReason());
    }

    private function fields(AttributeSyncPlan $plan): array
    {
        return array_map(static fn ($operation): string => $operation->getField(), array_merge(...$plan->getStages()));
    }
}
