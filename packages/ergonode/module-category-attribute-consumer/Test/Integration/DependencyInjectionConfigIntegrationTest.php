<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributeConsumer\Model\GraphQl\CategoryAttributeCodeLoader;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class DependencyInjectionConfigIntegrationTest extends TestCase
{
    public function testReadLoaderResolvesToConsumerImplementation(): void
    {
        self::assertInstanceOf(
            CategoryAttributeCodeLoader::class,
            Bootstrap::getObjectManager()->get(CategoryAttributeCodeLoaderInterface::class)
        );
    }
}
