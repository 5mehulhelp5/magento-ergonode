<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeRegistryRefresherInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Provider\ErgonodeCategoryAttributeProvider;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use PHPUnit\Framework\TestCase;

class CategoryAttributeSourcePreparationTest extends TestCase
{
    public function testDisabledAttributeSynchronizationDoesNotFetchRemoteDefinitions(): void
    {
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(false);
        $registry = $this->createMock(CategoryAttributeRegistryRefresherInterface::class);
        $registry->expects(self::never())->method('refresh');
        $provider = $this->createMock(ErgonodeCategoryAttributeProvider::class);
        $provider->expects(self::never())->method('reset');

        (new CategoryAttributeSourcePreparation($config, $registry, $provider))->prepare();
    }

    public function testPreparesOncePerRunAndRefreshesAgainForNextStreamRun(): void
    {
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $registry = $this->createMock(CategoryAttributeRegistryRefresherInterface::class);
        $registry->expects(self::exactly(2))->method('refresh')->willReturn(['imported' => 1]);
        $provider = $this->createMock(ErgonodeCategoryAttributeProvider::class);
        $provider->expects(self::exactly(2))->method('reset');
        $preparation = new CategoryAttributeSourcePreparation($config, $registry, $provider);

        $preparation->prepare();
        $preparation->ensurePrepared();
        $preparation->prepare();
        $preparation->ensurePrepared();
    }
}
