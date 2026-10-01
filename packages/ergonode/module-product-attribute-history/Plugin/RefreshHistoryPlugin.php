<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Plugin;

use Ergonode\AttributeConsumer\Api\AttributeSnapshotRefreshInterface;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;

class RefreshHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function aroundRefreshSnapshot(
        AttributeSnapshotRefreshInterface $subject,
        callable $proceed,
        ?string $cursor = null,
        ?int $pageSize = null
    ): array {
        unset($subject);
        return $this->capture->execute('refresh_snapshot', static fn (): array => $proceed($cursor, $pageSize));
    }
}
