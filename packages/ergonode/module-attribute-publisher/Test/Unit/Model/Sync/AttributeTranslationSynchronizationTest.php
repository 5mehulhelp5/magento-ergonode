<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\AttributePublisher\Model\Sync\AttributeOptionBatchSynchronizer;
use Ergonode\AttributePublisher\Model\Sync\AttributeOptionSynchronizer;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\AttributePublisher\Model\Sync\AttributeSynchronizer;
use Ergonode\AttributePublisher\Model\Sync\AttributeSyncPlanner;
use Ergonode\AttributePublisher\Model\Sync\MutationFailureMessageFormatter;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\Data\EntitySynchronizationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Api\AmbiguousMutationVerifierInterface;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttributeTranslationSynchronizationTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function routes(): array
    {
        return [
            'update rename' => ['update', true],
            'create only rename' => ['create_only', true],
            'update add' => ['update', false],
            'single option' => ['option', true],
            'batch recovery' => ['batch', true],
        ];
    }

    #[DataProvider('routes')]
    public function testOptionLanguageAbsentFromAttributeNameConverges(string $route, bool $exists): void
    {
        $names = ['en_US' => 'Red', 'pl_PL' => 'Old'];
        $desiredOption = new AttributeOptionState('red', ['en_US' => 'Red', 'pl_PL' => 'Nowy']);
        $loader = $this->createStub(AttributeStateLoader::class);
        $reads = [];
        $loader->method('load')->willReturnCallback(
            static function (string $code, array $languages) use (&$names, &$exists, &$reads): AttributeState {
                $reads[] = $languages;
                $visible = $languages === [] ? $names : array_intersect_key($names, array_flip($languages));
                return new AttributeState(
                    $code,
                    'select',
                    'LOCAL',
                    ['en_US' => 'Color'],
                    [],
                    [],
                    $exists ? [new AttributeOptionState('red', $visible)] : []
                );
            }
        );
        $batches = $this->createStub(MutationBatchPlannerInterface::class);
        $batches->method('plan')->willReturnCallback(static fn (array $operations): array => [
            new MutationBatch('mutation Test { __typename }', [], ['operation' => $operations[0]]),
        ]);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $calls = 0;
        $executor->expects(self::exactly($route === 'batch' ? 2 : 1))->method('execute')->willReturnCallback(
            static function (
                MutationBatchInterface $batch,
                ?AmbiguousMutationVerifierInterface $verifier = null
            ) use (
                &$names,
                &$exists,
                &$calls,
                $route
            ): SynchronizationResult {
                ++$calls;
                $operation = $batch->getOperationsByAlias()['operation'];
                if ($route === 'batch' && $calls === 1) {
                    return new SynchronizationResult([
                        new MutationResult(MutationResultInterface::STATUS_VALIDATION_FAILURE, 'operation', $operation),
                    ]);
                }
                $input = $operation->getVariables()['input']->getValue();
                self::assertContains(
                    $operation->getField(),
                    ['attributeSelectAddOption', 'attributeSelectSetOptionName']
                );
                foreach ($input['optionName'] ?? $input['option']['name'] as $translation) {
                    $names[$translation['language']] = $translation['value'];
                }
                $exists = true;
                self::assertNotNull($verifier);
                self::assertSame('applied', $verifier->verify($operation)->getStatus());
                return new SynchronizationResult([
                    new MutationResult(MutationResultInterface::STATUS_SUCCESS, 'operation', $operation),
                ]);
            }
        );
        $factory = new AttributeMutationFactory();
        $synchronizer = new AttributeSynchronizer($loader, new AttributeSyncPlanner($factory), $batches, $executor);
        $result = match ($route) {
            'option' => (new AttributeOptionSynchronizer($loader, $synchronizer))->synchronize('color', $desiredOption),
            'batch' => (new AttributeOptionBatchSynchronizer(
                $factory,
                $batches,
                $executor,
                $loader,
                $synchronizer,
                new MutationFailureMessageFormatter()
            ))->synchronizeBatch('color', [$desiredOption])['red'],
            default => $synchronizer->synchronize(new AttributeState(
                'color',
                'select',
                'LOCAL',
                ['en_US' => 'Color'],
                [],
                [],
                [$desiredOption]
            ), $route),
        };
        self::assertInstanceOf(EntitySynchronizationResultInterface::class, $result);
        self::assertTrue($result->isSuccessful(), (string)$result->getMessage());
        self::assertSame('Nowy', $names['pl_PL']);
        foreach ($reads as $languages) {
            self::assertContains('pl_PL', $languages);
        }
    }
}
