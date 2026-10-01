<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Test\Integration\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class TemplateStructureCascadeIntegrationTest extends TestCase
{
    public function testDeletingTemplateRemovesItsStructuralSnapshot(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $templateTable = $resource->getTableName('ergonode_template');
        $sectionTable = $resource->getTableName('ergonode_template_section');
        $attributeTable = $resource->getTableName('ergonode_template_attribute');

        $connection->insert($templateTable, [
            'code' => 'snapshot_template',
            'content_hash' => hash('sha256', 'snapshot_template'),
            'raw_json' => '{}',
        ]);
        $connection->insert($sectionTable, [
            'template_code' => 'snapshot_template',
            'section_code' => 'snapshot_section',
            'is_synthetic' => 0,
            'sort_order' => 0,
            'content_hash' => hash('sha256', 'snapshot_template:section'),
            'raw_json' => '{}',
        ]);
        $connection->insert($attributeTable, [
            'template_code' => 'snapshot_template',
            'section_code' => 'snapshot_section',
            'attribute_code' => 'snapshot_attribute',
            'sort_order' => 0,
        ]);

        $connection->delete($templateTable, ['code = ?' => 'snapshot_template']);

        self::assertSame(0, $this->countRows($resource, $sectionTable));
        self::assertSame(0, $this->countRows($resource, $attributeTable));
    }

    private function countRows(ResourceConnection $resource, string $table): int
    {
        return (int)$resource->getConnection()->fetchOne(
            $resource->getConnection()->select()
                ->from($table, ['COUNT(*)'])
                ->where('template_code = ?', 'snapshot_template')
        );
    }
}
