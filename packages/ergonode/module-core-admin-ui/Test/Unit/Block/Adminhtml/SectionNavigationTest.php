<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml\Test\Unit;

use Ergonode\CoreAdminUi\Api\NavigationGroupProviderInterface;
use Ergonode\CoreAdminUi\Block\Adminhtml\SectionNavigation;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use DOMDocument;
use DOMXPath;

class SectionNavigationTest extends TestCase
{
    public function testSortsGroupsAndLinksAndRetainsCurrentSection(): void
    {
        $entries = $this->createNavigation('languages')->getNavigationEntries();

        self::assertSame(
            ['Categories', 'Languages', 'Product', 'Readiness'],
            array_map(static fn (array $entry): string => (string)$entry['label'], $entries)
        );
        self::assertTrue($entries[1]['items'][0]['is_current']);
        self::assertSame('Product', (string)$entries[2]['label']);
        self::assertSame(['Attributes', 'List'], array_map(
            static fn (array $item): string => (string)$item['label'],
            $entries[2]['items']
        ));
        self::assertSame('/attributes', $entries[2]['url']);
    }

    public function testCurrentProductCannotNavigateButListRemainsAvailable(): void
    {
        $xpath = $this->renderNavigation($this->createNavigation('attributes'));

        self::assertSame(1, $xpath->query(
            '//a[normalize-space(.)="Product" and @aria-current="page" and not(@href)]'
        )->length);
        self::assertSame(1, $xpath->query(
            '//a[normalize-space(.)="Attributes" and @aria-disabled="true" and not(@href)]'
        )->length);
        self::assertSame(1, $xpath->query('//details//a[normalize-space(.)="List" and @href="/products"]')->length);
        self::assertSame(1, $xpath->query(
            '//div[contains(@class,"veui-section-navigation-active")]/details/summary'
        )->length);
    }

    public function testCurrentListRetainsLinkToAttributesAndStandaloneStaysVisible(): void
    {
        $xpath = $this->renderNavigation($this->createNavigation('products'));
        self::assertSame(1, $xpath->query('//a[normalize-space(.)="Product" and @href="/attributes"]')->length);
        self::assertSame(1, $xpath->query(
            '//a[normalize-space(.)="List" and @aria-current="page" and not(@href)]'
        )->length);

        $xpath = $this->renderNavigation($this->createNavigation('languages'));
        self::assertSame(1, $xpath->query(
            '//a[normalize-space(.)="Languages" and @aria-current="page" and not(@href)]'
        )->length);
    }

    public function testDeniedDestinationsRemainHiddenAndProductFallsBackToList(): void
    {
        $entries = $this->createNavigation('products', ['attributes', 'languages'])->getNavigationEntries();
        self::assertSame(['Categories', 'Product', 'Readiness'], array_map(
            static fn (array $entry): string => (string)$entry['label'],
            $entries
        ));
        self::assertSame('/products', $entries[1]['url']);
        self::assertCount(1, $entries[1]['items']);
        $xpath = $this->renderNavigation($this->createNavigation('products', ['attributes', 'languages']));
        self::assertSame(1, $xpath->query(
            '//a[normalize-space(.)="Product" and @aria-current="page" and not(@href)]'
        )->length);
    }

    public function testListPermissionDoesNotLeakWhenDenied(): void
    {
        $xpath = $this->renderNavigation($this->createNavigation('attributes', ['products']));
        self::assertSame(0, $xpath->query('//a[normalize-space(.)="List"]')->length);
        self::assertSame(1, $xpath->query('//a[normalize-space(.)="Product"]')->length);
    }

    public function testProductGroupIsHiddenWithoutAnyAllowedDestination(): void
    {
        $xpath = $this->renderNavigation($this->createNavigation('languages', ['attributes', 'products']));
        self::assertSame(0, $xpath->query('//a[normalize-space(.)="Product" or normalize-space(.)="List"]')->length);
    }

    public function testListWorksWithoutAttributeModuleContribution(): void
    {
        $block = $this->createNavigation('products');
        $property = new ReflectionProperty(SectionNavigation::class, 'sections');
        $sections = $property->getValue($block);
        unset($sections['attributes']);
        $property->setValue($block, $sections);
        $xpath = $this->renderNavigation($block);
        self::assertSame(1, $xpath->query(
            '//a[normalize-space(.)="Product" and @aria-current="page" and not(@href)]'
        )->length);
    }

    private function createNavigation(string $currentSection, array $denied = []): SectionNavigation
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            static fn (string $resource): bool => !in_array($resource, $denied, true)
        );
        $block = $this->getMockBuilder(SectionNavigation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthorization', 'getUrl'])
            ->getMock();
        $block->expects(self::atLeastOnce())->method('getAuthorization')->willReturn($authorization);
        $block->expects(self::atLeastOnce())->method('getUrl')->willReturnCallback(
            static fn (string $route): string => '/' . $route
        );
        $block->setData('current_section', $currentSection);
        $sections = [];
        $labels = ['readiness' => 'Readiness', 'attributes' => 'Product',
            'products' => 'List', 'languages' => 'Languages'];
        foreach ($labels as $code => $label) {
            $sections[$code] = [
                'label' => $label, 'icon' => null, 'route' => $code, 'resource' => $code, 'sort_order' => 0,
            ];
        }
        (new ReflectionProperty(SectionNavigation::class, 'sections'))->setValue($block, $sections);
        $provider = $this->createStub(NavigationGroupProviderInterface::class);
        $provider->method('getGroup')->willReturn([
            'label' => new Phrase('Categories'), 'icon' => null, 'url' => '/categories',
            'sections_label' => new Phrase('Category sections'), 'options_label' => new Phrase('Category options'),
            'section_codes' => ['categories'],
            'items' => [['label' => new Phrase('Tree'), 'icon' => null, 'url' => '/categories', 'is_current' => false]],
        ]);
        (new ReflectionProperty(SectionNavigation::class, 'navigationGroupProviders'))->setValue($block, [$provider]);
        return $block;
    }

    private function renderNavigation(SectionNavigation $block): DOMXPath
    {
        $escaper = $this->createStub(Escaper::class);
        foreach (['escapeHtml', 'escapeHtmlAttr', 'escapeUrl'] as $method) {
            $escaper->method($method)->willReturnCallback(
                static fn ($value): string => htmlspecialchars((string)$value)
            );
        }
        ob_start();
        include __DIR__ . '/../../../../view/adminhtml/templates/section-navigation.phtml';
        $markup = (string)ob_get_clean();
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($markup);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($document);
    }
}
