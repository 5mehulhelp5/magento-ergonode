<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerHistory\Plugin;

use Ergonode\AttributeConsumer\Api\AttributeSnapshotRefreshInterface;
use Ergonode\AttributeConsumer\Api\AttributeSnapshotRemoverInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeRegistryRefresherInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeSnapshotRemoverInterface;
use Ergonode\CategoryAttributeHistory\Api\HistoryOperationCaptureInterface;

class SnapshotHistoryPlugin
{
    public function __construct(private readonly HistoryOperationCaptureInterface $capture)
    {
    }

    /** @return array<string, mixed> */
    public function aroundRefresh(CategoryAttributeRegistryRefresherInterface $subject, callable $proceed): array
    {
        unset($subject);
        return $this->capture->execute('refresh_snapshot', static fn (): array => $proceed());
    }

    /** @return array<string, mixed> */
    public function aroundRefreshSnapshot(
        AttributeSnapshotRefreshInterface $subject,
        callable $proceed,
        ?string $cursor = null,
        ?int $pageSize = null
    ): array {
        unset($subject);
        return $this->capture->execute('refresh_snapshot', static fn (): array => $proceed($cursor, $pageSize));
    }

    /** @param callable(string): void $proceed */
    public function aroundRemove(
        CategoryAttributeSnapshotRemoverInterface|AttributeSnapshotRemoverInterface $subject,
        callable $proceed,
        string $attributeCode
    ): void {
        unset($subject);
        $this->capture->execute('delete_snapshot', static fn () => $proceed($attributeCode));
    }
}
