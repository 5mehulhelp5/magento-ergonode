<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Import;

use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Ergonode\TemplateConsumer\Model\Import\TemplateSynchronizationOperation;
use PHPUnit\Framework\TestCase;

class TemplateSynchronizationOperationTest extends TestCase
{
    public function testDelegatesSynchronizationAndCursorReset(): void
    {
        $synchronizer = $this->createMock(TemplateSynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('execute')->with(false);
        $synchronizer->expects(self::once())->method('resetCursor');
        $operation = new TemplateSynchronizationOperation($synchronizer);

        $operation->synchronize();
        $operation->resetCursor();
    }
}
