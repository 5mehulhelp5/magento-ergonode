<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Plugin;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class OptionRefreshHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /** @param callable(string): void $proceed */
    public function aroundRefreshOptions(
        AttributeCacheRefresherInterface $subject,
        callable $proceed,
        string $attributeCode
    ): void {
        unset($subject);
        $this->capture->execute('refresh_options', static fn () => $proceed($attributeCode));
    }
}
