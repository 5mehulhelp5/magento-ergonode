<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Plugin;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\SaveContext;
use Ergonode\CategoryAttributeConsumer\Api\MappingSynchronizationInterface;

class MappingSynchronizationPlugin
{
    public function __construct(private readonly MappingSynchronizationInterface $synchronization)
    {
    }

    /** @param callable(callable): array<string, mixed> $proceed
     * @param callable(): array<string, mixed> $save
     * @return array<string, mixed>
     */
    public function aroundExecute(SaveContext $subject, callable $proceed, callable $save): array
    {
        return $this->synchronization->execute(fn (): array => $proceed($save));
    }
}
