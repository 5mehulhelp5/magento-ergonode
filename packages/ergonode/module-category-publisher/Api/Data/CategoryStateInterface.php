<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Api\Data;

interface CategoryStateInterface
{
    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @return array<string, string>
     */
    public function getNames(): array;

    /**
     * @return CategorySynchronizationContributionInterface[]
     */
    public function getContributions(): array;

    /**
     * @return bool
     */
    public function isDeleted(): bool;
}
