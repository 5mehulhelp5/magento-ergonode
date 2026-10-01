<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\GraphQl;

use Ergonode\Publisher\Api\Exception\MutationBatchCapacityException;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class MutationBatchPlannerTest extends TestCase
{
    public function testChunksInInputOrderAtOperationLimit(): void
    {
        $planner = new MutationBatchPlanner($this->builder(2, 1024));

        $batches = $planner->plan([
            $this->operation('first', 'a'),
            $this->operation('second', 'b'),
            $this->operation('third', 'c'),
        ]);

        self::assertCount(2, $batches);
        self::assertSame(['first', 'second'], array_keys($batches[0]->getOperationsByAlias()));
        self::assertSame(['third'], array_keys($batches[1]->getOperationsByAlias()));
    }

    public function testChunksBeforeVariablesPayloadLimit(): void
    {
        $singleSize = strlen((new Json())->serialize(['first_input' => ['value' => str_repeat('x', 20)]]));
        $planner = new MutationBatchPlanner($this->builder(10, $singleSize + 5));

        $batches = $planner->plan([
            $this->operation('first', str_repeat('x', 20)),
            $this->operation('second', str_repeat('y', 20)),
        ]);

        self::assertCount(2, $batches);
        self::assertSame(['first'], array_keys($batches[0]->getOperationsByAlias()));
        self::assertSame(['second'], array_keys($batches[1]->getOperationsByAlias()));
    }

    public function testRejectsSingleOperationLargerThanPayloadLimit(): void
    {
        $planner = new MutationBatchPlanner($this->builder(10, 10));

        $this->expectException(MutationBatchCapacityException::class);
        $this->expectExceptionMessage('variables payload');

        $planner->plan([$this->operation('oversized', str_repeat('x', 20))]);
    }

    public function testEmptyPlanContainsExplicitNoOpBatch(): void
    {
        $batches = (new MutationBatchPlanner($this->builder(2, 1024)))->plan([]);

        self::assertCount(1, $batches);
        self::assertTrue($batches[0]->isEmpty());
    }

    private function operation(string $alias, string $value): MutationOperation
    {
        return new MutationOperation(
            'publish',
            ['input' => new MutationVariable('Input!', ['value' => $value])],
            ['id'],
            [],
            $alias
        );
    }

    private function builder(int $maxOperations, int $maxVariablesSizeBytes): MutationBatchBuilder
    {
        return new MutationBatchBuilder(
            new MutationAliasGenerator(),
            new Json(),
            $maxOperations,
            $maxVariablesSizeBytes
        );
    }
}
