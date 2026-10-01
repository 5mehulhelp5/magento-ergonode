<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisherAdminUi\Test\Unit\Model;

use Ergonode\AttributePublisherAdminUi\Model\ExistingSynchronizationNoticeResolver;
use Ergonode\Publisher\Api\Data\EntitySynchronizationResultInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use PHPUnit\Framework\TestCase;

class ExistingSynchronizationNoticeResolverTest extends TestCase
{
    private ExistingSynchronizationNoticeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ExistingSynchronizationNoticeResolver();
    }

    public function testRecognizesNoopAsExistingEntity(): void
    {
        $result = $this->createMock(EntitySynchronizationResultInterface::class);
        $result->method('getStatus')->willReturn(EntitySynchronizationResultInterface::STATUS_NOOP);

        self::assertTrue($this->resolver->wasExisting($result, 'create'));
    }

    public function testRecognizesCreationOperationAsNewEntity(): void
    {
        $result = $this->resultWithOperationKey('create');

        self::assertFalse($this->resolver->wasExisting($result, 'create'));
    }

    public function testRecognizesUpdateOperationsAsExistingEntity(): void
    {
        $result = $this->resultWithOperationKey('name');

        self::assertTrue($this->resolver->wasExisting($result, 'create'));
    }

    private function resultWithOperationKey(string $operationKey): SynchronizationResultInterface
    {
        $operation = $this->createStub(MutationOperationInterface::class);
        $operation->method('getMetadata')->willReturn(['operation_key' => $operationKey]);
        $mutationResult = $this->createStub(MutationResultInterface::class);
        $mutationResult->method('getOperation')->willReturn($operation);
        $result = $this->createStub(SynchronizationResultInterface::class);
        $result->method('getResults')->willReturn([$mutationResult]);

        return $result;
    }
}
