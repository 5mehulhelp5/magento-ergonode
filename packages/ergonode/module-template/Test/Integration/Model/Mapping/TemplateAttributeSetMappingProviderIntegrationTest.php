<?php

declare(strict_types=1);

namespace Ergonode\Template\Test\Integration\Model\Mapping;

use Ergonode\Template\Api\TemplateAttributeSetMappingProviderInterface;
use Ergonode\Template\Api\TemplateAttributeSetResolverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class TemplateAttributeSetMappingProviderIntegrationTest extends TestCase
{
    public function testPublicProvidersReadActiveMapping(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert($resource->getTableName('ergonode_template'), [
            'code' => 'shared-template',
            'attribute_set_id' => 321,
            'content_hash' => hash('sha256', 'shared-template'),
            'raw_json' => '{}',
        ]);
        $mappingProvider = Bootstrap::getObjectManager()->get(
            TemplateAttributeSetMappingProviderInterface::class
        );
        self::assertSame(
            [321 => 'shared-template'],
            $mappingProvider->getTemplateCodesByAttributeSetIds([321])
        );
        self::assertSame(
            321,
            Bootstrap::getObjectManager()->get(TemplateAttributeSetResolverInterface::class)
                ->resolveAttributeSetId('shared-template')
        );
    }

    public function testResolverIgnoresDeletedTemplate(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->insert($resource->getTableName('ergonode_template'), [
            'code' => 'deleted-template',
            'attribute_set_id' => 322,
            'is_deleted' => 1,
            'content_hash' => hash('sha256', 'deleted-template'),
            'raw_json' => '{}',
        ]);

        self::assertNull(
            Bootstrap::getObjectManager()->get(TemplateAttributeSetResolverInterface::class)
                ->resolveAttributeSetId('deleted-template')
        );
    }
}
