<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumerAdminUi\Test\Unit\Block\Adminhtml\Template;

use Ergonode\TemplateConsumerAdminUi\Block\Adminhtml\Template\Actions;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActionsTest extends TestCase
{
    #[DataProvider('permissions')]
    public function testActionPermissionsAreIndependent(
        bool $refresh,
        bool $deleteSnapshot,
        bool $synchronize
    ): void {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::exactly(3))->method('isAllowed')->willReturnMap([
            ['Ergonode_TemplateConsumer::template_refresh', null, $refresh],
            ['Ergonode_TemplateConsumer::template_save', null, $deleteSnapshot],
            ['Ergonode_TemplateConsumer::template_sync', null, $synchronize],
        ]);
        $block = new class ($authorization) extends Actions {
            public function __construct(
                private readonly AuthorizationInterface $authorization
            ) {
            }

            public function getAuthorization(): AuthorizationInterface
            {
                return $this->authorization;
            }
        };

        self::assertSame($refresh, $block->canRefreshTemplates());
        self::assertSame($deleteSnapshot, $block->canDeleteSnapshots());
        self::assertSame($synchronize, $block->canSynchronize());
    }

    /** @return array<string, array{bool, bool, bool}> */
    public static function permissions(): array
    {
        return [
            'mapping only' => [false, false, false],
            'refresh only' => [true, false, false],
            'save only' => [false, true, false],
            'sync only' => [false, false, true],
        ];
    }
}
