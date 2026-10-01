<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Import\TemplateStructureCacheWriter;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class TemplateStructureCacheWriterTest extends TestCase
{
    public function testSavesOnlyStructureTablesAndPreservesMappedGroup(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['template_code' => 'product', 'section_code' => 'details', 'content_hash' => 'old']],
            [['template_code' => 'product', 'section_code' => 'details', 'attribute_group_id' => '17']]
        );
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::exactly(2))->method('delete');
        $insertedSections = [];
        $connection->expects(self::exactly(2))->method('insertMultiple')->willReturnCallback(
            static function (string $table, array $rows) use (&$insertedSections): int {
                if ($table === 'ergonode_template_section') {
                    $insertedSections = $rows;
                }

                return count($rows);
            }
        );

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $result = (new TemplateStructureCacheWriter($resource, new Json()))->save([[
            'code' => 'product',
            'sections' => [[
                'code' => 'details',
                'is_synthetic' => false,
                'sort_order' => 1000,
                'attributes' => [['code' => 'color', 'sort_order' => 1]],
                'raw' => ['code' => 'details'],
                'hash' => 'new',
            ]],
        ]]);

        self::assertSame(['product' => ChangeReport::ACTION_UPDATED], $result);
        self::assertSame(17, $insertedSections[0]['attribute_group_id']);
    }
}
