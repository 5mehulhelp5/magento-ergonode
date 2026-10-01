<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Ergonode\Core\Api\SynchronizationOperationInterface;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;

class TemplateSynchronizationOperation implements SynchronizationOperationInterface
{
    public function __construct(
        private readonly TemplateSynchronizerInterface $templateSynchronizer
    ) {
    }

    public function synchronize(): void
    {
        $this->templateSynchronizer->execute(false);
    }

    public function resetCursor(): void
    {
        $this->templateSynchronizer->resetCursor();
    }
}
