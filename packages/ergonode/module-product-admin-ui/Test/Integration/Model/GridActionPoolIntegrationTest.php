<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Integration\Model;

use Ergonode\ProductAdminUi\Model\GridActionPool;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Module\Manager;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml'), AppIsolation(true)]
class GridActionPoolIntegrationTest extends TestCase
{
    public function testActualAdminDiProvidersRespectEveryDirectionProfile(): void
    {
        $manager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $manager);
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(true);
        $manager->addSharedInstance($authorization, AuthorizationInterface::class, true);
        foreach ([[true, true], [true, false], [false, true], [false, false]] as [$publish, $import]) {
            $modules = $this->createStub(Manager::class);
            $modules->method('isEnabled')->willReturnCallback(
                static fn (string $name): bool => match ($name) {
                    'Ergonode_ProductPublisher' => $publish,
                    'Ergonode_ProductConsumer' => $import,
                    default => true,
                }
            );
            $pool = $manager->create(GridActionPool::class, ['modules' => $modules]);
            $codes = array_column($pool->getActions(), 'code');
            sort($codes);
            $expected = array_keys(array_filter(['import' => $import, 'publish' => $publish]));
            self::assertSame($expected, $codes);
        }
    }
}
