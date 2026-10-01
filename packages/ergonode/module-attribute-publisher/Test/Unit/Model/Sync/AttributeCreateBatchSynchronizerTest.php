<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeSynchronizationResult;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\AttributePublisher\Model\Sync\AttributeCreateBatchSynchronizer;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\AttributePublisher\Model\Sync\MutationFailureMessageFormatter;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class AttributeCreateBatchSynchronizerTest extends TestCase
{
    public function testMapsCompatibleExistingAttributeWithoutFailingOtherAliasesInOneBatch(): void
    {
        $color = $this->state('color');
        $categoryGear = $this->state('category_gear');
        $collar = $this->state('collar');
        $colorOperation = new MutationOperation('attributeCreateSelect', [], ['attribute.code']);
        $categoryGearOperation = new MutationOperation('attributeCreateText', [], ['attribute.code']);
        $collarOperation = new MutationOperation('attributeCreateText', [], ['attribute.code']);
        $operations = [$colorOperation, $categoryGearOperation, $collarOperation];
        $mutationFactory = $this->createMock(AttributeMutationFactory::class);
        $mutationFactory->method('supports')->willReturn(true);
        $mutationFactory->expects(self::exactly(3))->method('create')->willReturnMap([
            [$color, $colorOperation],
            [$categoryGear, $categoryGearOperation],
            [$collar, $collarOperation],
        ]);
        $batchPlanner = new MutationBatchPlanner(new MutationBatchBuilder(
            new MutationAliasGenerator(),
            new Json(),
            50,
            524288
        ));
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())
            ->method('execute')
            ->with(self::callback(static function (MutationBatchInterface $batch) use ($operations): bool {
                self::assertStringStartsWith('mutation PublishBatch', $batch->getDocument());
                self::assertSame($operations, array_values($batch->getOperationsByAlias()));

                return true;
            }))
            ->willReturn(new SynchronizationResult([
                new MutationResult(
                    MutationResultInterface::STATUS_VALIDATION_FAILURE,
                    'color',
                    $colorOperation,
                    null,
                    [[
                        'message' => 'Attribute color already exists.',
                        'extensions' => ['code' => 'BAD_USER_INPUT'],
                    ]]
                ),
                new MutationResult(
                    MutationResultInterface::STATUS_SUCCESS,
                    'categoryGear',
                    $categoryGearOperation
                ),
                new MutationResult(MutationResultInterface::STATUS_SUCCESS, 'collar', $collarOperation),
            ]));
        $remoteColor = $this->state('color');
        $stateLoader = $this->createMock(AttributeStateLoader::class);
        $stateLoader->expects(self::once())
            ->method('load')
            ->with('color', ['en_US'])
            ->willReturn($remoteColor);
        $existingResult = new AttributeSynchronizationResult(
            AttributeSynchronizationResultInterface::STATUS_NOOP,
            AttributeSynchronizationResultInterface::REFERENCE_PRESENT
        );
        $attributeSynchronizer = $this->createMock(AttributeSynchronizerInterface::class);
        $attributeSynchronizer->expects(self::once())
            ->method('synchronize')
            ->with($color, AttributeSynchronizerInterface::MODE_CREATE_ONLY)
            ->willReturn($existingResult);

        $results = (new AttributeCreateBatchSynchronizer(
            $mutationFactory,
            $batchPlanner,
            $executor,
            $stateLoader,
            $attributeSynchronizer,
            new MutationFailureMessageFormatter()
        ))->synchronizeBatch([$color, $categoryGear, $collar]);

        self::assertSame(['color', 'category_gear', 'collar'], array_keys($results));
        self::assertSame(AttributeSynchronizationResultInterface::STATUS_NOOP, $results['color']->getStatus());
        self::assertSame($colorOperation, $results['color']->getResults()[0]->getOperation());
        self::assertStringContainsString(
            '[BAD_USER_INPUT] Attribute color already exists.',
            (string)$results['color']->getMessage()
        );
        self::assertSame(
            AttributeSynchronizationResultInterface::STATUS_SUCCESS,
            $results['category_gear']->getStatus()
        );
        self::assertSame(AttributeSynchronizationResultInterface::STATUS_SUCCESS, $results['collar']->getStatus());
    }

    private function state(string $code): AttributeStateInterface
    {
        $state = $this->createStub(AttributeStateInterface::class);
        $state->method('getCode')->willReturn($code);
        $state->method('getType')->willReturn($code === 'color' ? 'select' : 'text');
        $state->method('getNames')->willReturn(['en_US' => ucfirst($code)]);

        return $state;
    }
}
