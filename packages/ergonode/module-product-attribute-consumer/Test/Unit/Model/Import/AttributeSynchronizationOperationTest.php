<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationProcessInterface;
use Ergonode\ProductAttributeConsumer\Model\Import\AttributeSynchronizationOperation;
use PHPUnit\Framework\TestCase;

class AttributeSynchronizationOperationTest extends TestCase
{
    public function testDelegatesSynchronizationAndCursorReset(): void
    {
        $process = $this->createMock(AttributeSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('executeUntilComplete');
        $process->expects(self::once())->method('reset');
        $operation = new AttributeSynchronizationOperation($process);

        $operation->synchronize();
        $operation->resetCursor();
    }
}
