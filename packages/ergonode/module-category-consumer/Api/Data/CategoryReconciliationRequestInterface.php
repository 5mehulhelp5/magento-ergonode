<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api\Data;

interface CategoryReconciliationRequestInterface
{
    public const string MODE_PREVIEW = 'preview';
    public const string MODE_APPLY = 'apply';

    /** @return int */
    public function getCategoryTreeId(): int;

    /**
     * @param int $categoryTreeId
     * @return $this
     */
    public function setCategoryTreeId(int $categoryTreeId): self;

    /** @return string */
    public function getMode(): string;

    /**
     * @param string $mode
     * @return $this
     */
    public function setMode(string $mode): self;

    /** @return array<int, array{ergonode_code: string, magento_category_id: int}> */
    public function getDraftMappings(): array;

    /**
     * @param array<int, array{ergonode_code: string, magento_category_id: int}> $draftMappings
     * @return $this
     */
    public function setDraftMappings(array $draftMappings): self;

    /** @return array<int, array{source: string, identifier: string, active: bool}> */
    public function getDraftVisibility(): array;

    /**
     * @param array<int, array{source: string, identifier: string, active: bool}> $draftVisibility
     * @return $this
     */
    public function setDraftVisibility(array $draftVisibility): self;
}
