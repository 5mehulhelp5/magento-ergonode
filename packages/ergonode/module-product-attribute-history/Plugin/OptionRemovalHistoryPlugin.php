<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Plugin;

use Ergonode\AttributeConsumer\Api\OptionSnapshotRemoverInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class OptionRemovalHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /** @param callable(string, string): void $proceed */
    public function aroundRemove(
        OptionSnapshotRemoverInterface $subject,
        callable $proceed,
        string $attributeCode,
        string $optionCode
    ): void {
        unset($subject);
        $this->capture->execute('delete_option_snapshot', static fn () => $proceed($attributeCode, $optionCode));
    }
}
