<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Test\Integration;

use Ergonode\CategoryAttributePublisherAdminUi\Model\Mapping\SourceMetadata;
use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SourceMetadataDiTest extends TestCase
{
    public function testMetadataResolvesTheBatchContractWithoutRemoteReadsWhenDisabled(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('allowsWrites')->willReturn(false);
        $source = Bootstrap::getObjectManager()->create(SourceMetadata::class, ['config' => $config]);
        self::assertSame([], $source->getAttributes());
        self::assertSame([], $source->getOptions('color'));
    }
}
