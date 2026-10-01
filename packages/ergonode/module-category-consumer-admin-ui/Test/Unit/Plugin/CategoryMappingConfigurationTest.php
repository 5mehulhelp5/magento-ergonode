<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Test\Unit\Plugin;

use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Ergonode\CategoryConsumer\Api\CategoryStreamAvailabilityProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeSyncMetadataProviderInterface;
use Ergonode\CategoryConsumerAdminUi\Plugin\CategoryMappingConfiguration;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class CategoryMappingConfigurationTest extends TestCase
{
    public function testAddsInboundControlsWithoutReplacingNeutralConfiguration(): void
    {
        $metadata = $this->createStub(CategoryTreeSyncMetadataProviderInterface::class);
        $metadata->method('get')->willReturn(['cursor' => 'next']);
        $availability = $this->createStub(CategoryStreamAvailabilityProviderInterface::class);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnArgument(0);
        $config = (new CategoryMappingConfiguration($metadata, $availability, $url))->afterGetConfig(
            $this->createStub(CategoryTreeMappingUiProvider::class),
            ['category_tree_id' => 7, 'categories' => [], 'status' => ['error' => null]]
        );
        self::assertTrue($config['has_sync_cursor']);
        self::assertSame(['error' => null], $config['status']);
        self::assertSame('ergonode/category_tree/sync', $config['urls']['sync']);
        self::assertArrayNotHasKey('auto_map', $config['urls']);
    }
}
