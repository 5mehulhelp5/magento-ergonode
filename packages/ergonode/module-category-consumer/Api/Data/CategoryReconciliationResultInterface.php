<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api\Data;

interface CategoryReconciliationResultInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getCategories(): array;

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return $this
     */
    public function setCategories(array $categories): self;

    /** @return array<int, array<string, mixed>> */
    public function getMagentoCategories(): array;

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return $this
     */
    public function setMagentoCategories(array $categories): self;

    /** @return string[] */
    public function getConflicts(): array;

    /**
     * @param string[] $conflicts
     * @return $this
     */
    public function setConflicts(array $conflicts): self;

    /** @return array<string, int> */
    public function getStats(): array;

    /**
     * @param array<string, int> $stats
     * @return $this
     */
    public function setStats(array $stats): self;

    /** @return int[] */
    public function getDeleteCandidates(): array;

    /**
     * @param int[] $categoryIds
     * @return $this
     */
    public function setDeleteCandidates(array $categoryIds): self;
}
