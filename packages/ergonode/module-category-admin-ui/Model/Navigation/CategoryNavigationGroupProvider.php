<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Model\Navigation;

use Ergonode\CategoryAdminUi\Api\CategoryNavigationItemProviderInterface;
use Ergonode\CoreAdminUi\Api\NavigationGroupProviderInterface;
use InvalidArgumentException;

class CategoryNavigationGroupProvider implements NavigationGroupProviderInterface
{
    /** @var CategoryNavigationItemProviderInterface[] */
    private array $itemProviders;

    /**
     * @param CategoryNavigationItemProviderInterface[] $itemProviders
     * @param list<string> $sectionCodes
     */
    public function __construct(
        array $itemProviders = [],
        private readonly array $sectionCodes = [CategoryTreeNavigationItemProvider::SECTION_CODE]
    ) {
        foreach ($itemProviders as $provider) {
            if (!$provider instanceof CategoryNavigationItemProviderInterface) {
                throw new InvalidArgumentException(
                    'Category navigation item providers must implement their API contract.'
                );
            }
        }
        foreach ($this->sectionCodes as $sectionCode) {
            if (!is_string($sectionCode) || trim($sectionCode) === '') {
                throw new InvalidArgumentException('Category navigation section codes must be non-empty strings.');
            }
        }
        $this->itemProviders = array_values($itemProviders);
    }

    public function getGroup(string $currentSection): ?array
    {
        $items = [];
        foreach ($this->itemProviders as $provider) {
            $items = [...$items, ...$provider->getItems($currentSection)];
        }
        if ($items === []) {
            return null;
        }

        return [
            'label' => __('Categories'),
            'icon' => 'list-tree',
            'url' => $items[0]['url'],
            'sections_label' => __('Category sections'),
            'options_label' => __('Category options'),
            'section_codes' => $this->sectionCodes,
            'items' => $items,
        ];
    }
}
