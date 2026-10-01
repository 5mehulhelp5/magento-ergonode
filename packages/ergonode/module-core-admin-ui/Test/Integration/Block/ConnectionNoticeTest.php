<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Integration\Block;

use Magento\Framework\View\Element\Text;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class ConnectionNoticeTest extends TestCase
{
    /**
     * @magentoConfigFixture default/ergonode_connection/general/enabled 0
     * @magentoConfigFixture default/ergonode_connection/general/environment test
     */
    public function testOptInPreventsRenderingContentAndInitializers(): void
    {
        $layout = Bootstrap::getObjectManager()->create(LayoutInterface::class);
        $layout->getUpdate()->load(['default', 'ergonode_connection_required']);
        $layout->getUpdate()->addUpdate('<container name="content" label="Content"/>');
        $layout->generateXml();
        $layout->generateElements();
        $layout->addBlock(Text::class, 'test.workspace', 'content')->setText('WORKSPACE_INITIALIZER');
        $html = $layout->renderElement('content');
        self::assertStringContainsString('data-role="connection-notice"', $html);
        self::assertStringContainsString('This view is unavailable.', $html);
        self::assertStringNotContainsString('Check again', $html);
        self::assertStringNotContainsString('Configure the Ergonode GraphQL URL.', $html);
        self::assertStringNotContainsString('WORKSPACE_INITIALIZER', $html);
        self::assertStringNotContainsString('text/x-magento-init', $html);
    }
}
