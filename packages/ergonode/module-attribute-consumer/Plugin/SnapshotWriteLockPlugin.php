<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Plugin;

use Ergonode\AttributeConsumer\Model\Import\AttributeCacheRefresher;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Import\OptionCacheReconciler;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionSnapshotInterface;
use Ergonode\AttributeConsumer\Model\Snapshot\AttributeSnapshotRemover;
use Ergonode\AttributeConsumer\Model\Snapshot\OptionSnapshotRemover;
use Ergonode\AttributeConsumer\Model\Snapshot\SnapshotWriteLock;

/** All entry points that mutate shared snapshots use the same process-shared lock. */
class SnapshotWriteLockPlugin
{
    public function __construct(private readonly SnapshotWriteLock $lock)
    {
    }

    public function aroundRefreshOptions(
        AttributeCacheRefresher $subject,
        callable $proceed,
        string $attributeCode
    ): void {
        unset($subject);
        $this->lock->execute(static fn () => $proceed($attributeCode));
    }

    public function aroundRefreshOptionsWriteScope(
        AttributeCacheRefresher $subject,
        callable $proceed,
        string $attributeCode
    ): void {
        unset($subject);
        $this->lock->execute(static fn () => $proceed($attributeCode));
    }

    /**
     * @param list<array<string, mixed>> $attributes
     * @return array<string, string>
     */
    public function aroundSaveAttributes(
        AttributeCacheWriter $subject,
        callable $proceed,
        array $attributes
    ): array {
        unset($subject);
        return $this->lock->execute(static fn () => $proceed($attributes));
    }

    /**
     * @param list<array<string, mixed>> $options
     * @return array<string, string>
     */
    public function aroundSaveOptions(
        AttributeCacheWriter $subject,
        callable $proceed,
        string $attributeCode,
        array $options
    ): array {
        unset($subject);
        return $this->lock->execute(static fn () => $proceed($attributeCode, $options));
    }

    /**
     * @param iterable<array<string, mixed>> $attributes
     * @param array<string, mixed> $state
     * @return string[]
     */
    public function aroundReplace(
        AttributeDefinitionSnapshotInterface $subject,
        callable $proceed,
        iterable $attributes,
        array $state
    ): array {
        unset($subject);
        return $this->lock->execute(static fn () => $proceed($attributes, $state));
    }

    /** @param string[] $currentOptionCodes */
    public function aroundReconcile(
        OptionCacheReconciler $subject,
        callable $proceed,
        string $attributeCode,
        array $currentOptionCodes
    ): int {
        unset($subject);
        return $this->lock->execute(static fn () => $proceed($attributeCode, $currentOptionCodes));
    }

    public function aroundRemove(
        AttributeSnapshotRemover|OptionSnapshotRemover $subject,
        callable $proceed,
        string $attributeCode,
        ?string $optionCode = null
    ): void {
        unset($subject);
        $this->lock->execute(static fn () => $proceed($attributeCode, ...($optionCode === null ? [] : [$optionCode])));
    }
}
