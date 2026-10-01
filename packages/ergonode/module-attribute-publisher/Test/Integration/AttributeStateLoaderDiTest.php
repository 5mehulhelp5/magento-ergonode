<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Integration;

use Ergonode\AttributePublisher\Api\AttributeBatchStateLoaderInterface;
use Ergonode\AttributePublisher\Api\AttributeStateLoaderInterface;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class AttributeStateLoaderDiTest extends TestCase
{
    public function testSingleAndBatchContractsResolveToTheSharedReader(): void
    {
        $objects = Bootstrap::getObjectManager();
        $single = $objects->get(AttributeStateLoaderInterface::class);
        $batch = $objects->get(AttributeBatchStateLoaderInterface::class);
        self::assertInstanceOf(AttributeStateLoader::class, $batch);
        self::assertSame($single, $batch);
    }
}
