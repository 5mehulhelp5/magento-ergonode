<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Import;

use Ergonode\Core\Api\SynchronizationOperationInterface;
use Ergonode\Core\Model\Import\SynchronizationOperationExecutor;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use stdClass;

class SynchronizationOperationExecutorTest extends TestCase
{
    public function testDelegatesOperationsRegisteredForProcess(): void
    {
        $operation = $this->createMock(SynchronizationOperationInterface::class);
        $operation->expects(self::once())->method('synchronize');
        $operation->expects(self::once())->method('resetCursor');
        $executor = new SynchronizationOperationExecutor(['attributeStream' => $operation]);

        self::assertTrue($executor->isAvailable('attributeStream'));
        self::assertFalse($executor->isAvailable('category_tree_stream'));
        $executor->synchronize('attributeStream');
        $executor->resetCursor('attributeStream');
    }

    public function testRejectsUnavailableProcess(): void
    {
        $this->expectException(LocalizedException::class);

        (new SynchronizationOperationExecutor())->synchronize('unknown');
    }

    public function testRejectsInvalidOperationRegistration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SynchronizationOperationExecutor(['attributeStream' => new stdClass()]);
    }
}
