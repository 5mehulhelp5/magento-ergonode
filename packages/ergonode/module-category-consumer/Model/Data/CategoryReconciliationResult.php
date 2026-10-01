<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Data;

use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationResultInterface;

class CategoryReconciliationResult implements CategoryReconciliationResultInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $categories = [];

    /** @var array<int, array<string, mixed>> */
    private array $magentoCategories = [];

    /** @var string[] */
    private array $conflicts = [];

    /** @var array<string, int> */
    private array $stats = [];

    /** @var int[] */
    private array $deleteCandidates = [];

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setCategories(array $categories): CategoryReconciliationResultInterface
    {
        $this->categories = $categories;

        return $this;
    }

    public function getMagentoCategories(): array
    {
        return $this->magentoCategories;
    }

    public function setMagentoCategories(array $categories): CategoryReconciliationResultInterface
    {
        $this->magentoCategories = $categories;

        return $this;
    }

    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function setConflicts(array $conflicts): CategoryReconciliationResultInterface
    {
        $this->conflicts = $conflicts;

        return $this;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function setStats(array $stats): CategoryReconciliationResultInterface
    {
        $this->stats = $stats;

        return $this;
    }

    public function getDeleteCandidates(): array
    {
        return $this->deleteCandidates;
    }

    public function setDeleteCandidates(array $categoryIds): CategoryReconciliationResultInterface
    {
        $this->deleteCandidates = $categoryIds;

        return $this;
    }
}
