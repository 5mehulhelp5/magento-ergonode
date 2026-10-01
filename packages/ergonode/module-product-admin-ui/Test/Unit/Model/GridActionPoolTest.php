<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Model;

use Ergonode\ProductAdminUi\Api\GridActionProviderInterface;
use Ergonode\ProductAdminUi\Model\GridActionPool;
use Magento\Framework\Module\Manager;
use PHPUnit\Framework\TestCase;

class GridActionPoolTest extends TestCase
{
    public function testDisabledDirectionIsNeverResolvedAndOtherDirectionStillWorks(): void
    {
        $modules = $this->createStub(Manager::class);
        $modules->method('isEnabled')->willReturnCallback(static fn (string $name): bool => $name !== 'Publisher');
        $publisher = $this->createMock(GridActionProviderInterface::class);
        $publisher->expects(self::never())->method('getConfiguration');
        $consumer = $this->createMock(GridActionProviderInterface::class);
        $consumer->expects(self::once())->method('getConfiguration')->willReturn(['label' => 'Import']);
        $pool = new GridActionPool($modules, [
            'publish' => ['modules' => ['Publisher'], 'provider' => $publisher],
            'import' => ['modules' => ['Consumer'], 'provider' => $consumer],
        ]);
        self::assertSame([['code' => 'import', 'label' => 'Import']], $pool->getActions());
    }

    public function testNoDirectionAndDeniedPermissionYieldNoActions(): void
    {
        $modules = $this->createStub(Manager::class);
        $modules->method('isEnabled')->willReturn(true);
        self::assertSame([], (new GridActionPool($modules))->getActions());
        $provider = $this->createStub(GridActionProviderInterface::class);
        $provider->method('getConfiguration')->willReturn(null);
        self::assertSame([], (new GridActionPool($modules, [
            'import' => ['modules' => ['Consumer'], 'provider' => $provider],
        ]))->getActions());
    }
}
