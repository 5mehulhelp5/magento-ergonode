<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Block\Adminhtml\CategoryTreeMapping\Test\Unit;

use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\TestCase;
use Ergonode\CategoryAdminUi\Block\Adminhtml\CategoryTreeMapping\Index;

class IndexTest extends TestCase
{
    public function testCategoryTreeSettingsLinkRequiresSavePermission(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())
            ->method('isAllowed')
            ->with('Ergonode_CategoryConsumer::category_tree_save')
            ->willReturn(false);

        $block = new class ($authorization) extends Index {
            public function __construct(
                private readonly AuthorizationInterface $authorization
            ) {
            }

            public function getAuthorization(): AuthorizationInterface
            {
                return $this->authorization;
            }
        };

        self::assertFalse($block->canEditCategoryTree());
    }

    public function testErgonodeTreeRefreshRequiresManagePermission(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())
            ->method('isAllowed')
            ->with('Ergonode_CategoryConsumer::category_tree_manage')
            ->willReturn(true);

        $block = new class ($authorization) extends Index {
            public function __construct(
                private readonly AuthorizationInterface $authorization
            ) {
            }

            public function getAuthorization(): AuthorizationInterface
            {
                return $this->authorization;
            }
        };

        self::assertTrue($block->canRefreshCategoryTrees());
    }
}
