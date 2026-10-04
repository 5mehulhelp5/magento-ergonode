<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

use Ergonode\ProductConsumer\Model\Pipeline\BatchContext;

/** A processor runs once per batch and may share prepared data with later phases. */
interface BatchProcessorInterface
{
    public function process(BatchContext $context): void;
}
