<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Api;

use Magento\Framework\Phrase;

interface CategoryNavigationItemProviderInterface
{
    /**
     * @param string $currentSection
     * @return list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     */
    public function getItems(string $currentSection): array;
}
