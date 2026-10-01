<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\GraphQl;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use stdClass;
use Ergonode\Publisher\Api\Data\MutationVariableInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;

class MutationBatchBuilderTest extends TestCase
{
    public function testBuildsParseableMixedMutationWithVariablesAndMetadataCorrelation(): void
    {
        $alpha = new MutationOperation(
            'upsertAlpha',
            ['input' => new MutationVariable('AlphaInput!', ['code' => 'material', 'label' => 'Silk'])],
            ['id', 'validationErrors.message'],
            ['domain' => 'alpha', 'source_id' => 41]
        );
        $beta = new MutationOperation(
            'upsertBeta',
            ['input' => new MutationVariable('BetaInput!', ['code' => 'chairs'])],
            ['id'],
            ['domain' => 'beta']
        );

        $batch = $this->builder()->build([$alpha, $beta]);

        Parser::parse($batch->getDocument());
        self::assertSame([
            'upsertAlpha_input' => ['code' => 'material', 'label' => 'Silk'],
            'upsertBeta_input' => ['code' => 'chairs'],
        ], $batch->getVariables());
        self::assertStringContainsString('$upsertAlpha_input: AlphaInput!', $batch->getDocument());
        self::assertStringContainsString(
            'upsertAlpha: upsertAlpha(input: $upsertAlpha_input)',
            $batch->getDocument()
        );
        self::assertStringNotContainsString('material', $batch->getDocument());
        self::assertStringNotContainsString('Silk', $batch->getDocument());
        self::assertSame($alpha, $batch->getOperationsByAlias()['upsertAlpha']);
        self::assertSame(41, $batch->getOperationsByAlias()['upsertAlpha']->getMetadata()['source_id']);
    }

    public function testGeneratesCollisionFreeAliasesAndVariableNames(): void
    {
        $first = $this->operation('publish', 'same');
        $second = $this->operation('publish', 'same');
        $third = $this->operation('publish', 'same_2');

        $batch = $this->builder()->build([$first, $second, $third]);

        self::assertSame(['same', 'same_2', 'same_2_2'], array_keys($batch->getOperationsByAlias()));
        self::assertSame(
            ['same_input', 'same_2_input', 'same_2_2_input'],
            array_keys($batch->getVariables())
        );
        Parser::parse($batch->getDocument());
    }

    public function testBuildsDocumentContainingExactlyOneOperation(): void
    {
        $batch = $this->builder()->build([$this->operation('publish', 'single')]);

        $document = Parser::parse($batch->getDocument());

        self::assertCount(1, $document->definitions);
        $operation = $document->definitions[0];
        self::assertInstanceOf(OperationDefinitionNode::class, $operation);
        self::assertCount(1, $operation->selectionSet->selections);
        self::assertSame(['single'], array_keys($batch->getOperationsByAlias()));
    }

    public function testBuildsExplicitEmptyBatch(): void
    {
        $batch = $this->builder()->build([]);

        self::assertTrue($batch->isEmpty());
        self::assertSame('', $batch->getDocument());
        self::assertSame([], $batch->getVariables());
    }

    public function testRejectsInvalidGraphQlIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid GraphQL mutation field');

        $this->builder()->build([
            new MutationOperation('publish-product', [], ['id']),
        ]);
    }

    public function testRejectsInvalidVariableTypesAndResponseFields(): void
    {
        try {
            $this->builder()->build([
                new MutationOperation('publish', ['input' => new MutationVariable('Input! injected', [])], ['id']),
            ]);
            self::fail('Invalid variable type should fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Invalid GraphQL variable type', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid GraphQL response field');
        $this->builder()->build([
            new MutationOperation('publish', [], ['id } mutation Injected {']),
        ]);
    }

    public function testRejectsRepeatedOperationInstance(): void
    {
        $operation = $this->operation('publish', 'same');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('same mutation operation instance');

        $this->builder()->build([$operation, $operation]);
    }

    public function testRejectsMutableAndNonJsonVariableValues(): void
    {
        try {
            new MutationVariable('Input!', ['nested' => new stdClass()]);
            self::fail('Mutable nested value should fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('JSON-compatible', $exception->getMessage());
        }

        $unsafeVariable = new class implements MutationVariableInterface {
            public function getType(): string
            {
                return 'Input!';
            }

            public function getValue(): mixed
            {
                return NAN;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('floats must be finite');
        new MutationOperation('publish', ['input' => $unsafeVariable], ['id']);
    }

    public function testRejectsMutableCorrelationMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('immutable scalar values');

        new MutationOperation('publish', [], ['id'], ['source' => new stdClass()]);
    }

    public function testSnapshotsReferencedValuesForDeepImmutability(): void
    {
        $variableSource = 'before';
        $metadataSource = 'source-before';
        $responseFieldSource = 'id';
        $variableValue = ['nested' => ['value' => &$variableSource]];
        $metadata = ['source' => &$metadataSource];
        $responseFields = [&$responseFieldSource];

        $operation = new MutationOperation(
            'publish',
            ['input' => new MutationVariable('Input!', $variableValue)],
            $responseFields,
            $metadata
        );
        $variableSource = 'after';
        $metadataSource = 'source-after';
        $responseFieldSource = 'code';

        self::assertSame(
            ['nested' => ['value' => 'before']],
            $operation->getVariables()['input']->getValue()
        );
        self::assertSame(['source' => 'source-before'], $operation->getMetadata());
        self::assertSame(['id'], $operation->getResponseFields());
    }

    private function operation(string $field, string $alias): MutationOperation
    {
        return new MutationOperation(
            $field,
            ['input' => new MutationVariable('Input!', ['code' => $alias])],
            ['id'],
            [],
            $alias
        );
    }

    private function builder(): MutationBatchBuilder
    {
        return new MutationBatchBuilder(new MutationAliasGenerator(), new Json());
    }
}
