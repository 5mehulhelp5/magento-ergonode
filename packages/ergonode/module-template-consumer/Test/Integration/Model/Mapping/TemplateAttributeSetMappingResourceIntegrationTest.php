<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Integration\Model\Mapping;

use Ergonode\TemplateConsumer\Model\Mapping\TemplateAttributeSetMappingResource;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class TemplateAttributeSetMappingResourceIntegrationTest extends TestCase
{
    public function testAttributeSetCanBeMappedToOnlyOneTemplate(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insertMultiple($resource->getTableName('ergonode_template'), [
            $this->templateRow('template_a'),
            $this->templateRow('template_b'),
        ]);
        $attributeSetId = Bootstrap::getObjectManager()
            ->get(AttributeSetResource::class)
            ->getDefaultProductAttributeSetId();
        $mappingResource = Bootstrap::getObjectManager()->get(TemplateAttributeSetMappingResource::class);

        $mappingResource->assign('template_a', $attributeSetId);

        try {
            $mappingResource->assign('template_b', $attributeSetId);
            self::fail('Expected duplicate Magento attribute set mapping to be rejected.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('already mapped', $exception->getMessage());
        }

        $mappings = $connection->fetchPairs(
            $connection->select()
                ->from($resource->getTableName('ergonode_template'), ['code', 'attribute_set_id'])
                ->where('code IN (?)', ['template_a', 'template_b'])
                ->order('code ASC')
        );
        self::assertSame($attributeSetId, (int)$mappings['template_a']);
        self::assertNull($mappings['template_b']);
    }

    /** @return array<string, string> */
    private function templateRow(string $code): array
    {
        return [
            'code' => $code,
            'content_hash' => hash('sha256', $code),
            'raw_json' => '{}',
        ];
    }
}
