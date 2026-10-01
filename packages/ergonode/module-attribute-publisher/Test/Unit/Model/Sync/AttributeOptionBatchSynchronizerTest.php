<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\AttributePublisher\Model\Data\AttributeSynchronizationResult;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\AttributePublisher\Model\Sync\AttributeOptionBatchSynchronizer;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\AttributePublisher\Model\Sync\MutationFailureMessageFormatter;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttributeOptionBatchSynchronizerTest extends TestCase
{
    public function testMapsExistingOptionWithoutFailingOtherAliasesInOneBatch(): void
    {
        $red = new AttributeOptionState('red', ['en_US' => 'Red']);
        $blue = new AttributeOptionState('blue', ['en_US' => 'Blue']);
        $green = new AttributeOptionState('green', ['en_US' => 'Green']);
        $emptyRemote = $this->attribute([]);
        $remoteWithRed = $this->attribute([$red]);
        $stateLoader = $this->createMock(AttributeStateLoader::class);
        $stateLoader->expects(self::exactly(2))
            ->method('load')
            ->with('color', ['en_US'])
            ->willReturnOnConsecutiveCalls($emptyRemote, $remoteWithRed);
        $optionSynchronizer = $this->createMock(AttributeSynchronizerInterface::class);
        $optionSynchronizer->expects(self::never())->method('synchronize');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())
            ->method('execute')
            ->with(self::callback(static function (MutationBatchInterface $batch): bool {
                self::assertStringStartsWith('mutation PublishBatch', $batch->getDocument());
                self::assertCount(3, $batch->getOperationsByAlias());
                $operations = array_values($batch->getOperationsByAlias());
                self::assertSame(
                    ['attributeSelectAddOption', 'attributeSelectAddOption', 'attributeSelectAddOption'],
                    array_map(static fn ($operation): string => $operation->getField(), $operations)
                );
                self::assertSame(
                    ['red', 'blue', 'green'],
                    array_map(
                        static function ($operation): string {
                            $input = $operation->getVariables()['input']->getValue();
                            return $input['option']['code'];
                        },
                        $operations
                    )
                );

                return true;
            }))
            ->willReturnCallback(static function (MutationBatchInterface $batch): SynchronizationResult {
                $operations = array_values($batch->getOperationsByAlias());

                return new SynchronizationResult([
                    new MutationResult(
                        MutationResultInterface::STATUS_VALIDATION_FAILURE,
                        'red',
                        $operations[0],
                        null,
                        [[
                            'message' => 'Option red already exists.',
                            'extensions' => ['code' => 'BAD_USER_INPUT'],
                        ]]
                    ),
                    new MutationResult(MutationResultInterface::STATUS_SUCCESS, 'blue', $operations[1]),
                    new MutationResult(MutationResultInterface::STATUS_SUCCESS, 'green', $operations[2]),
                ]);
            });

        $results = (new AttributeOptionBatchSynchronizer(
            new AttributeMutationFactory(),
            new MutationBatchPlanner(new MutationBatchBuilder(new MutationAliasGenerator(), new Json(), 50, 524288)),
            $executor,
            $stateLoader,
            $optionSynchronizer,
            new MutationFailureMessageFormatter()
        ))->synchronizeBatch('color', [$red, $blue, $green]);

        self::assertSame(['red', 'blue', 'green'], array_keys($results));
        self::assertSame(AttributeOptionSynchronizationResultInterface::STATUS_NOOP, $results['red']->getStatus());
        self::assertStringContainsString(
            '[BAD_USER_INPUT] Option red already exists.',
            (string)$results['red']->getMessage()
        );
        self::assertSame(
            AttributeOptionSynchronizationResultInterface::STATUS_SUCCESS,
            $results['blue']->getStatus()
        );
        self::assertSame(
            AttributeOptionSynchronizationResultInterface::STATUS_SUCCESS,
            $results['green']->getStatus()
        );
    }

    public function testDoesNotSendMutationWhenAllOptionsAlreadyMatch(): void
    {
        $red = new AttributeOptionState('red', ['en_US' => 'Red']);
        $stateLoader = $this->createMock(AttributeStateLoader::class);
        $stateLoader->expects(self::once())->method('load')->willReturn($this->attribute([$red]));
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $results = (new AttributeOptionBatchSynchronizer(
            new AttributeMutationFactory(),
            new MutationBatchPlanner(new MutationBatchBuilder(new MutationAliasGenerator(), new Json(), 50, 524288)),
            $executor,
            $stateLoader,
            $this->createStub(AttributeSynchronizerInterface::class),
            new MutationFailureMessageFormatter()
        ))->synchronizeBatch('color', [$red]);

        self::assertSame(AttributeOptionSynchronizationResultInterface::STATUS_NOOP, $results['red']->getStatus());
        self::assertSame([], $results['red']->getResults());
    }

    public function testRepeatedBatchIgnoresTranslationsOutsideEachOptionsRequestedLanguages(): void
    {
        $desired = [
            new AttributeOptionState('red', ['en_US' => 'Red']),
            new AttributeOptionState('blue', ['pl_PL' => 'Niebieski']),
        ];
        $remote = $this->attribute([
            new AttributeOptionState('red', ['en_US' => 'Red', 'pl_PL' => 'Czerwony']),
            new AttributeOptionState('blue', ['en_US' => 'Blue', 'pl_PL' => 'Niebieski']),
        ]);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')
            ->with('color', ['en_US', 'pl_PL'])->willReturn($remote);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $synchronizer = new AttributeOptionBatchSynchronizer(
            new AttributeMutationFactory(),
            new MutationBatchPlanner(new MutationBatchBuilder(new MutationAliasGenerator(), new Json(), 50, 524288)),
            $executor,
            $loader,
            $this->createStub(AttributeSynchronizerInterface::class),
            new MutationFailureMessageFormatter()
        );

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $results = $synchronizer->synchronizeBatch('color', $desired);
            self::assertSame(['red', 'blue'], array_keys($results));
            foreach ($results as $result) {
                self::assertSame(AttributeOptionSynchronizationResultInterface::STATUS_NOOP, $result->getStatus());
                self::assertSame(
                    AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT,
                    $result->getReferenceStatus()
                );
                self::assertSame([], $result->getResults());
            }
        }
    }

    /** @return array<string, array{array<string, string>}> */
    public static function differingRequestedNames(): array
    {
        return [
            'changed requested translation' => [['en_US' => 'Blue', 'pl_PL' => 'Stary']],
            'missing requested translation' => [['en_US' => 'Blue']],
        ];
    }

    /** @param array<string, string> $remoteNames */
    #[DataProvider('differingRequestedNames')]
    public function testBatchWritesOnlyTheOptionWithMissingOrChangedRequestedTranslation(array $remoteNames): void
    {
        $desired = [
            new AttributeOptionState('red', ['en_US' => 'Red']),
            new AttributeOptionState('blue', ['pl_PL' => 'Niebieski']),
        ];
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::once())->method('load')->with('color', ['en_US', 'pl_PL'])
            ->willReturn($this->attribute([
                new AttributeOptionState('red', ['en_US' => 'Red', 'pl_PL' => 'Czerwony']),
                new AttributeOptionState('blue', $remoteNames),
            ]));
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->willReturnCallback(
            static function (MutationBatchInterface $batch): SynchronizationResult {
                $operations = $batch->getOperationsByAlias();
                self::assertCount(1, $operations);
                $operation = reset($operations);
                self::assertSame('attributeSelectSetOptionName', $operation->getField());
                self::assertSame([
                    'code' => 'color',
                    'optionCode' => 'blue',
                    'optionName' => [['language' => 'pl_PL', 'value' => 'Niebieski']],
                ], $operation->getVariables()['input']->getValue());

                return new SynchronizationResult([
                    new MutationResult(MutationResultInterface::STATUS_SUCCESS, key($operations), $operation),
                ]);
            }
        );
        $results = (new AttributeOptionBatchSynchronizer(
            new AttributeMutationFactory(),
            new MutationBatchPlanner(new MutationBatchBuilder(new MutationAliasGenerator(), new Json(), 50, 524288)),
            $executor,
            $loader,
            $this->createStub(AttributeSynchronizerInterface::class),
            new MutationFailureMessageFormatter()
        ))->synchronizeBatch('color', $desired);

        self::assertSame([], $results['red']->getResults());
        self::assertTrue($results['red']->isSuccessful());
        self::assertCount(1, $results['blue']->getResults());
        self::assertTrue($results['blue']->isSuccessful());
    }

    public function testVerifiesManyConflictsWithOneReadAndOneGroupedSynchronization(): void
    {
        $desired = [
            new AttributeOptionState('123', ['en_US' => 'Red']),
            new AttributeOptionState('blue', ['en_US' => 'Blue']),
        ];
        $remote = $this->attribute([
            new AttributeOptionState('123', ['en_US' => 'Old red', 'pl_PL' => 'Czerwony']),
            new AttributeOptionState('blue', ['en_US' => 'Old blue', 'pl_PL' => 'Niebieski']),
        ]);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')
            ->with('color', ['en_US'])->willReturnOnConsecutiveCalls($this->attribute([]), $remote);
        $synchronizer = $this->createMock(AttributeSynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronize')->with(
            self::callback(static function (AttributeState $state): bool {
                self::assertCount(2, $state->getOptions());
                self::assertSame('123', $state->getOptions()[0]->getCode());
                self::assertSame(
                    ['en_US' => 'Red', 'pl_PL' => 'Czerwony'],
                    $state->getOptions()[0]->getNames()
                );
                self::assertSame(
                    ['en_US' => 'Blue', 'pl_PL' => 'Niebieski'],
                    $state->getOptions()[1]->getNames()
                );
                return true;
            }),
            AttributeSynchronizerInterface::MODE_UPDATE,
            $remote
        )->willReturn(new AttributeSynchronizationResult('success', 'present'));
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->willReturnCallback(
            static function (MutationBatchInterface $batch): SynchronizationResult {
                $failures = [];
                foreach ($batch->getOperationsByAlias() as $operation) {
                    $failures[] = new MutationResult(
                        MutationResultInterface::STATUS_VALIDATION_FAILURE,
                        $operation->getVariables()['input']->getValue()['option']['code'],
                        $operation
                    );
                }
                return new SynchronizationResult($failures);
            }
        );
        $results = (new AttributeOptionBatchSynchronizer(
            new AttributeMutationFactory(),
            new MutationBatchPlanner(new MutationBatchBuilder(new MutationAliasGenerator(), new Json(), 50, 524288)),
            $executor,
            $loader,
            $synchronizer,
            new MutationFailureMessageFormatter()
        ))->synchronizeBatch('color', $desired);
        self::assertCount(2, $results);
        self::assertSame('noop', $results['123']->getStatus());
        self::assertSame('noop', $results['blue']->getStatus());
    }

    /** @param AttributeOptionState[] $options */
    private function attribute(array $options): AttributeState
    {
        return new AttributeState(
            'color',
            'select',
            'GLOBAL',
            ['en_US' => 'Color'],
            [],
            [],
            $options
        );
    }
}
