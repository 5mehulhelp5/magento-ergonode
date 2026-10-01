<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Plugin;

use Ergonode\AttributeConsumer\Api\AttributeSnapshotRemoverInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class SnapshotRemovalHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @param callable(string): void $proceed
     */
    public function aroundRemove(
        AttributeSnapshotRemoverInterface $subject,
        callable $proceed,
        string $attributeCode
    ): void {
        unset($subject);
        $this->capture->execute('delete_snapshot', static fn () => $proceed($attributeCode));
    }
}
