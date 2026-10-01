<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Test\Unit\Model;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\TemplatePublisher\Model\GraphQl\TemplateMutationFactory;
use Ergonode\TemplatePublisher\Model\TemplateCreator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateCreatorTest extends TestCase
{
    public function testCreatesMissingTemplate(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::once())->method('queryWriteScope')->willReturn(['template' => null]);
        $batch = $this->createStub(MutationBatchInterface::class);
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::once())->method('plan')->willReturn([$batch]);
        $execution = $this->createStub(SynchronizationResultInterface::class);
        $execution->method('isSuccessful')->willReturn(true);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->with($batch)->willReturn($execution);
        $this->creator($client, $planner, $executor)->create(
            'summer_collection',
            ['pl_PL' => 'Kolekcja letnia']
        );
    }

    public function testExistingTemplateIsAnIdempotentNoOp(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn(['template' => ['code' => 'existing']]);
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::never())->method('plan');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');
        $this->creator($client, $planner, $executor)->create('existing', ['pl_PL' => 'Existing']);
    }

    #[DataProvider('invalidCodeProvider')]
    public function testRejectsInvalidCodeBeforeIo(string $code): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::never())->method('queryWriteScope');
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::never())->method('plan');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Template code may contain lowercase letters, numbers and underscores');

        $this->creator($client, $planner, $executor)->create($code, ['pl_PL' => 'Template']);
    }

    /** @return array<string, array{string}> */
    public static function invalidCodeProvider(): array
    {
        return [
            'empty' => ['   '],
            'uppercase' => ['Summer'],
            'dash' => ['summer-template'],
            'too long' => [str_repeat('a', 129)],
        ];
    }

    /** @param array<string, string> $names */
    #[DataProvider('emptyNamesProvider')]
    public function testRejectsNamesEmptyAfterNormalization(array $names): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::never())->method('queryWriteScope');
        $planner = $this->createMock(MutationBatchPlannerInterface::class);
        $planner->expects(self::never())->method('plan');
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::never())->method('execute');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('At least one template name is required.');

        $this->creator($client, $planner, $executor)->create('summer_template', $names);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function emptyNamesProvider(): array
    {
        return [
            'no names' => [[]],
            'blank name' => [['pl_PL' => '   ']],
            'blank language' => [['  ' => 'Template']],
        ];
    }

    public function testRejectsUnsuccessfulMutationResult(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn(['template' => null]);
        $batch = $this->createStub(MutationBatchInterface::class);
        $planner = $this->createStub(MutationBatchPlannerInterface::class);
        $planner->method('plan')->willReturn([$batch]);
        $execution = $this->createStub(SynchronizationResultInterface::class);
        $execution->method('isSuccessful')->willReturn(false);
        $executor = $this->createMock(MutationExecutorInterface::class);
        $executor->expects(self::once())->method('execute')->with($batch)->willReturn($execution);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to create Ergonode template "summer_template".');

        $this->creator($client, $planner, $executor)->create(
            'summer_template',
            ['pl_PL' => 'Template']
        );
    }

    private function creator(
        GraphQlWriteScopeQueryClientInterface $client,
        MutationBatchPlannerInterface $planner,
        MutationExecutorInterface $executor
    ): TemplateCreator {
        return new TemplateCreator(
            $client,
            new TemplateMutationFactory(),
            $planner,
            $executor
        );
    }
}
