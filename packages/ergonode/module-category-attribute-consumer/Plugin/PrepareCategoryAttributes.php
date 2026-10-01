<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Plugin;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;

class PrepareCategoryAttributes
{
    public function __construct(private readonly CategoryAttributeSourcePreparation $preparation)
    {
    }

    public function beforeExecute(CategoryEntityStreamImporter $subject, bool $resetCursor = false): ?array
    {
        unset($subject, $resetCursor);
        $this->preparation->prepare();

        return null;
    }
}
