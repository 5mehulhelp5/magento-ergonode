<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisherAdminUi\Test\Unit\Block\Adminhtml;

use Ergonode\TemplatePublisherAdminUi\Block\Adminhtml\TemplatePublisher;
use Ergonode\TemplatePublisherAdminUi\Controller\Adminhtml\Template\Create;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplatePublisherTest extends TestCase
{
    #[DataProvider('permissions')]
    public function testTemplateCreationRequiresEndpointPermission(bool $isAllowed): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())
            ->method('isAllowed')
            ->with(Create::ADMIN_RESOURCE)
            ->willReturn($isAllowed);
        $block = new class ($authorization) extends TemplatePublisher {
            public function __construct(
                private readonly AuthorizationInterface $authorization
            ) {
            }

            public function getAuthorization(): AuthorizationInterface
            {
                return $this->authorization;
            }
        };

        self::assertSame($isAllowed, $block->canCreateTemplate());
    }

    /** @return array<string, array{bool}> */
    public static function permissions(): array
    {
        return [
            'template save is allowed' => [true],
            'template save is denied' => [false],
        ];
    }
}
