<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Import;

use Ergonode\Core\Api\SynchronizationOperationInterface;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationProcessInterface;

class AttributeSynchronizationOperation implements SynchronizationOperationInterface
{
    public function __construct(
        private readonly AttributeSynchronizationProcessInterface $attributeImportProcess
    ) {
    }

    public function synchronize(): void
    {
        $this->attributeImportProcess->executeUntilComplete();
    }

    public function resetCursor(): void
    {
        $this->attributeImportProcess->reset();
    }
}
