<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\GraphQl;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Ergonode\Core\Model\GraphQl\MutationGuard;
use Ergonode\Core\Model\Update\UpdateGuard;

class MutationGuardTest extends TestCase
{
    public function testAuthorizesMutationThroughUpdateGuard(): void
    {
        $updateGuard = $this->createMock(UpdateGuard::class);
        $updateGuard->expects(self::once())->method('authorize')->willReturn('dedicated-update-key');

        self::assertSame(
            'dedicated-update-key',
            (new MutationGuard($updateGuard))->authorize("# comment\nmutation UpdateAttribute { updateAttribute }")
        );
    }

    public function testRejectsQueryBeforeCheckingUpdateConfiguration(): void
    {
        $updateGuard = $this->createMock(UpdateGuard::class);
        $updateGuard->expects(self::never())->method('authorize');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Only GraphQL mutation operations are allowed on the update path.');

        (new MutationGuard($updateGuard))->authorize('query Attribute { attribute { code } }');
    }

    public function testAuthorizesReadInUpdateCredentialScope(): void
    {
        $updateGuard = $this->createMock(UpdateGuard::class);
        $updateGuard->expects(self::once())->method('authorize')->willReturn('dedicated-update-key');

        self::assertSame(
            'dedicated-update-key',
            (new MutationGuard($updateGuard))->authorizeUpdateScope()
        );
    }
}
