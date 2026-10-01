<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Data;

use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;

class CategoryReconciliationRequest implements CategoryReconciliationRequestInterface
{
    private int $categoryTreeId = 0;
    private string $mode = self::MODE_PREVIEW;

    /** @var array<int, array{ergonode_code: string, magento_category_id: int}> */
    private array $draftMappings = [];

    /** @var array<int, array{source: string, identifier: string, active: bool}> */
    private array $draftVisibility = [];

    public function getCategoryTreeId(): int
    {
        return $this->categoryTreeId;
    }

    public function setCategoryTreeId(int $categoryTreeId): CategoryReconciliationRequestInterface
    {
        $this->categoryTreeId = $categoryTreeId;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): CategoryReconciliationRequestInterface
    {
        $this->mode = $mode;

        return $this;
    }

    public function getDraftMappings(): array
    {
        return $this->draftMappings;
    }

    public function setDraftMappings(array $draftMappings): CategoryReconciliationRequestInterface
    {
        $this->draftMappings = $draftMappings;

        return $this;
    }

    public function getDraftVisibility(): array
    {
        return $this->draftVisibility;
    }

    public function setDraftVisibility(array $draftVisibility): CategoryReconciliationRequestInterface
    {
        $this->draftVisibility = $draftVisibility;

        return $this;
    }
}
