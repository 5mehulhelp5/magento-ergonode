<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface TemplateSynchronizerInterface
{
    /**
     * @param bool $resetCursor Clear legacy cursor state before the full template list synchronization.
     * @return array{
     *     events: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     cursor: string|null
     * }
     * @throws LocalizedException
     */
    public function execute(bool $resetCursor = false): array;

    /**
     * Clear the persisted template cursor without importing templates. Synchronization always reads the full list.
     *
     * @return void
     * @throws LocalizedException
     */
    public function resetCursor(): void;
}
