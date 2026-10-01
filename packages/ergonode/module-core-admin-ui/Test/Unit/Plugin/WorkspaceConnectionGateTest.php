<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Plugin;

use Ergonode\CoreAdminUi\Model\WorkspaceAvailability;
use Magento\Framework\View\Element\AbstractBlock;
use Ergonode\CoreAdminUi\Plugin\Adminhtml\WorkspaceConnectionGate;
use Magento\Framework\View\Layout;
use PHPUnit\Framework\TestCase;

class WorkspaceConnectionGateTest extends TestCase
{
    public function testBlockedContentNeverRendersWorkspaceOrExtensionInitializers(): void
    {
        $notice = $this->createMock(AbstractBlock::class);
        $availability = $this->createStub(WorkspaceAvailability::class);
        $availability->method('getProblems')->willReturn(['Connection disabled']);
        $notice->expects(self::once())->method('toHtml')->willReturn('<section>Connection disabled</section>');
        $layout = $this->createMock(Layout::class);
        $layout->expects(self::once())->method('getBlock')->with('ergonode.connection.notice')->willReturn($notice);
        $result = (new WorkspaceConnectionGate($availability))->aroundRenderElement(
            $layout,
            static function (): string {
                self::fail('Blocked workspace must not render.');
            },
            'content'
        );
        self::assertSame('<section>Connection disabled</section>', $result);
    }

    public function testReadyContentPreservesRenderArguments(): void
    {
        $notice = $this->createMock(AbstractBlock::class);
        $availability = $this->createStub(WorkspaceAvailability::class);
        $availability->method('getProblems')->willReturn([]);
        $notice->expects(self::never())->method('toHtml');
        $layout = $this->createMock(Layout::class);
        $layout->expects(self::once())->method('getBlock')->willReturn($notice);
        $result = (new WorkspaceConnectionGate($availability))->aroundRenderElement(
            $layout,
            static function (string $name, bool $useCache): string {
                self::assertSame('content', $name);
                self::assertFalse($useCache);
                return 'workspace';
            },
            'content',
            false
        );
        self::assertSame('workspace', $result);
    }

    public function testPagesWithoutOptInRemainAvailable(): void
    {
        $availability = $this->createMock(WorkspaceAvailability::class);
        $availability->expects(self::never())->method('getProblems');
        $layout = $this->createMock(Layout::class);
        $layout->expects(self::once())->method('getBlock')->willReturn(false);
        self::assertSame('diagnostics', (new WorkspaceConnectionGate($availability))->aroundRenderElement(
            $layout,
            static fn (): string => 'diagnostics',
            'content'
        ));
    }

    public function testHeaderAndMenuDoNotTriggerTheConnectionCheck(): void
    {
        $availability = $this->createMock(WorkspaceAvailability::class);
        $availability->expects(self::never())->method('getProblems');
        $layout = $this->createMock(Layout::class);
        $layout->expects(self::never())->method('getBlock');
        self::assertSame('header', (new WorkspaceConnectionGate($availability))->aroundRenderElement(
            $layout,
            static fn (): string => 'header',
            'header'
        ));
    }
}
