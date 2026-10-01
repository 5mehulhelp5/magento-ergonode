<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml;

use Ergonode\CoreAdminUi\Api\NavigationGroupProviderInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;
use InvalidArgumentException;

class SectionNavigation extends Template
{
    public const string SECTION_ATTRIBUTES = 'attributes';
    public const string SECTION_PRODUCTS = 'products';
    public const string SECTION_OPTIONS = 'options';
    public const string SECTION_LANGUAGES = 'languages';
    public const string SECTION_READINESS = 'readiness';
    public const string SECTION_SYNCHRONIZATIONS = 'synchronizations';

    /** @var array<string, array{label: string, icon: string|null, route: string, resource: string, sort_order: int}> */
    private array $sections;

    /** @var NavigationGroupProviderInterface[] */
    private array $navigationGroupProviders;

    /**
     * @var list<array{
     *     label: Phrase,
     *     icon: string|null,
     *     url: string,
     *     sections_label: Phrase,
     *     options_label: Phrase,
     *     section_codes: list<string>,
     *     items: list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     * }>|null
     */
    private ?array $navigationGroups = null;

    /**
     * @param array<string, array{
     *     label: string,
     *     icon?: string|null,
     *     route: string,
     *     resource: string,
     *     sort_order?: int|numeric-string
     * }> $sections
     * @param NavigationGroupProviderInterface[] $navigationGroupProviders
     */
    public function __construct(
        Context $context,
        array $sections = [],
        array $navigationGroupProviders = [],
        array $data = []
    ) {
        foreach ($sections as $code => $section) {
            if (!is_string($code)
                || !is_array($section)
                || !isset($section['label'], $section['route'], $section['resource'])
                || !is_string($section['label'])
                || !is_string($section['route'])
                || !is_string($section['resource'])
                || (isset($section['icon']) && !is_string($section['icon']))
                || (isset($section['sort_order']) && !is_numeric($section['sort_order']))
            ) {
                throw new InvalidArgumentException('Section navigation items must use the documented array contract.');
            }
            $sections[$code]['icon'] = $section['icon'] ?? null;
            $sections[$code]['sort_order'] = (int)($section['sort_order'] ?? 0);
        }
        foreach ($navigationGroupProviders as $provider) {
            if (!$provider instanceof NavigationGroupProviderInterface) {
                throw new InvalidArgumentException(
                    'Navigation group providers must implement their API contract.'
                );
            }
        }
        uasort(
            $sections,
            static fn (array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']
        );
        $this->sections = $sections;
        $this->navigationGroupProviders = array_values($navigationGroupProviders);
        parent::__construct($context, $data);
    }

    /**
     * @return list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     */
    public function getNavigationItems(): array
    {
        $currentSection = (string)$this->getData('current_section');
        $groupedSections = $this->getGroupedSections();
        $items = [];

        foreach ($this->sections as $sectionCode => $section) {
            if (in_array($sectionCode, $groupedSections, true)
                || !$this->getAuthorization()->isAllowed($section['resource'])
            ) {
                continue;
            }

            $items[] = [
                'icon' => $section['icon'] ?? null,
                'label' => __($section['label']),
                'url' => $this->getUrl($section['route']),
                'is_current' => $sectionCode === $currentSection,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{
     *     label: Phrase,
     *     icon: string|null,
     *     url: string,
     *     sections_label: Phrase,
     *     options_label: Phrase,
     *     section_codes: list<string>,
     *     items: list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     * }>
     */
    public function getNavigationGroups(): array
    {
        if ($this->navigationGroups !== null) {
            return $this->navigationGroups;
        }

        $groups = [];
        $currentSection = (string)$this->getData('current_section');
        foreach ($this->navigationGroupProviders as $provider) {
            $group = $provider->getGroup($currentSection);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        $this->navigationGroups = $groups;

        return $this->navigationGroups;
    }

    /**
     * @return list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     */
    public function getAttributeNavigationItems(): array
    {
        return $this->getGroupedNavigationItems([self::SECTION_ATTRIBUTES, self::SECTION_PRODUCTS]);
    }

    public function getAttributeSwitcherLabel(): Phrase
    {
        return __($this->sections[self::SECTION_ATTRIBUTES]['label'] ?? 'Product');
    }

    public function isAttributeSwitcher(): bool
    {
        foreach ([self::SECTION_ATTRIBUTES, self::SECTION_PRODUCTS] as $sectionCode) {
            $section = $this->sections[$sectionCode] ?? null;
            if ($section !== null && $this->getAuthorization()->isAllowed($section['resource'])) {
                return true;
            }
        }

        return false;
    }

    public function getAttributeUrl(): string
    {
        foreach ([self::SECTION_ATTRIBUTES, self::SECTION_PRODUCTS] as $sectionCode) {
            $section = $this->sections[$sectionCode] ?? null;
            if ($section !== null && $this->getAuthorization()->isAllowed($section['resource'])) {
                return $this->getUrl($section['route']);
            }
        }

        return '';
    }

    /**
     * @return list<array{
     *     label: Phrase, icon: string|null, url: string,
     *     sections_label: Phrase, options_label: Phrase,
     *     items: list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     * }>
     */
    public function getNavigationEntries(): array
    {
        $entries = $this->getNavigationGroups();
        $attributeItems = $this->getAttributeNavigationItems();
        if ($attributeItems !== []) {
            $entries[] = [
                'label' => $this->getAttributeSwitcherLabel(),
                'icon' => 'attribution-pen',
                'url' => $this->getAttributeUrl(),
                'sections_label' => __('Attribute sections'),
                'options_label' => __('Attribute options'),
                'items' => $attributeItems,
            ];
        }
        foreach ($this->getNavigationItems() as $item) {
            $entries[] = [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'url' => $item['url'],
                'sections_label' => $item['label'],
                'options_label' => $item['label'],
                'items' => [$item],
            ];
        }
        $compareLabels = static fn (array $left, array $right): int =>
            strnatcasecmp((string)$left['label'], (string)$right['label']);
        usort($entries, $compareLabels);
        foreach ($entries as &$entry) {
            usort($entry['items'], $compareLabels);
        }
        return $entries;
    }

    public function isStandalone(): bool
    {
        return (bool)$this->getData('standalone');
    }

    /**
     * @param list<string> $sectionCodes
     * @return list<array{icon: string|null, label: Phrase, url: string, is_current: bool}>
     */
    private function getGroupedNavigationItems(array $sectionCodes): array
    {
        $currentSection = (string)$this->getData('current_section');
        $items = [];

        foreach ($sectionCodes as $sectionCode) {
            $section = $this->sections[$sectionCode] ?? null;
            if ($section === null || !$this->getAuthorization()->isAllowed($section['resource'])) {
                continue;
            }

            $items[] = [
                'icon' => $section['icon'] ?? null,
                'label' => $sectionCode === self::SECTION_ATTRIBUTES ? __('Attributes') : __($section['label']),
                'url' => $this->getUrl($section['route']),
                'is_current' => $sectionCode === $currentSection,
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function getGroupedSections(): array
    {
        $sections = [];

        foreach ($this->getNavigationGroups() as $group) {
            $sections = [...$sections, ...$group['section_codes']];
        }
        if ($this->isAttributeSwitcher()) {
            $sections = [...$sections, self::SECTION_ATTRIBUTES, self::SECTION_PRODUCTS];
        }
        return $sections;
    }

    protected $_template = 'Ergonode_CoreAdminUi::section-navigation.phtml';
}
