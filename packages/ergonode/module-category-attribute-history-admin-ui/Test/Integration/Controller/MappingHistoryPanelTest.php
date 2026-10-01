<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistoryAdminUi\Test\Integration\Controller;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Magento\Framework\Acl\Builder;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\View\Result\PageFactory;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class MappingHistoryPanelTest extends AbstractBackendController
{
    protected $resource = 'Ergonode_CategoryAttributeHistory::view';
    protected $uri = 'backend/ergonode/category_attribute_history/operations';
    protected $httpMethod = 'GET';

    /**
     * @magentoConfigFixture default/ergonode_connection/general/enabled 1
     * @magentoConfigFixture default/ergonode_connection/general/environment test
     * @magentoConfigFixture default/ergonode_connection/general/mode read
     * @magentoConfigFixture default/ergonode_connection/test/url https://ergonode.example.invalid/graphql
     * @magentoConfigFixture default/ergonode_connection/test/consumer/api_key fixture-key
     */
    public function testMappingPageEmbedsSharedPanelInMagentoColumnWithCategoryRoutes(): void
    {
        $html = $this->renderMappingPage();
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $panels = (new DOMXPath($document))->query(
            '//section[@data-source-panel="magento"]//aside[contains(@class,"veah-mapping-history")]'
        );
        self::assertCount(1, $panels);
        $panel = $panels->item(0);
        self::assertInstanceOf(DOMElement::class, $panel);
        $config = json_decode($panel->getAttribute('data-mage-init'), true, 512, JSON_THROW_ON_ERROR);
        $urls = $config['Ergonode_CoreAdminUi/js/history/panel']['urls'];
        self::assertStringContainsString('/ergonode/category_attribute_history/operations', $urls['operations']);
        self::assertStringContainsString('/ergonode/category_attribute_history/index', $urls['history']);
    }

    /**
     * @magentoConfigFixture default/ergonode_connection/general/enabled 1
     * @magentoConfigFixture default/ergonode_connection/general/environment test
     * @magentoConfigFixture default/ergonode_connection/general/mode read
     * @magentoConfigFixture default/ergonode_connection/test/url https://ergonode.example.invalid/graphql
     * @magentoConfigFixture default/ergonode_connection/test/consumer/api_key fixture-key
     */
    public function testHistoryPermissionCanHidePanelWithoutDenyingMappingPage(): void
    {
        $acl = $this->_objectManager->get(Builder::class)->getAcl();
        $user = $this->_auth->getUser();
        self::assertTrue(method_exists($user, 'getRoles'));
        $acl->deny($user->getRoles(), 'Ergonode_CategoryAttributeHistory::view');

        $html = $this->renderMappingPage();
        self::assertStringContainsString('ergonode-category-attribute-mapping', $html);
        self::assertStringNotContainsString('data-role="history-operations"', $html);
    }

    private function renderMappingPage(): string
    {
        $page = $this->_objectManager->get(PageFactory::class)->create();
        $page->addHandle('ergonode_category_attribute_index');

        // The integration profile disables mapping UI modules; load the actual host layout explicitly.
        $modulePath = $this->_objectManager->get(ComponentRegistrar::class)->getPath(
            ComponentRegistrar::MODULE,
            'Ergonode_CategoryAttributeAdminUi'
        );
        $hostLayout = simplexml_load_file(
            $modulePath . '/view/adminhtml/layout/ergonode_category_attribute_index.xml'
        );
        foreach ($hostLayout->body->children() as $element) {
            $page->getLayout()->getUpdate()->addUpdate($element->asXML());
        }

        return $page->getLayout()->getOutput();
    }
}
