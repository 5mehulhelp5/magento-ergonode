<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Api;

use Magento\Framework\Phrase;

interface NavigationGroupProviderInterface
{
    /**
     * @param string $currentSection
     * @return array{
     *     label: Phrase,
     *     icon: string|null,
     *     url: string,
     *     sections_label: Phrase,
     *     options_label: Phrase,
     *     section_codes: list<string>,
     *     items: list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     * }|null
     */
    public function getGroup(string $currentSection): ?array;
}
