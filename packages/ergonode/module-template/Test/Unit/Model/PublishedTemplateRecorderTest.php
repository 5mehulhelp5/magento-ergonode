<?php

declare(strict_types=1);

namespace Ergonode\Template\Test\Unit\Model;

use Ergonode\Template\Model\PublishedTemplateRecorder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class PublishedTemplateRecorderTest extends TestCase
{
    public function testRecordsKnownPublishedIdentityWithoutOverwritingExistingMappingOrSnapshot(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $json = new Json();
        $payload = $json->serialize(['code' => 'shoes', 'name' => [['language' => 'en_GB', 'value' => 'Shoes']]]);
        $connection->expects(self::once())->method('insertOnDuplicate')->with(
            'ergonode_template',
            ['code' => 'shoes', 'raw_json' => $payload, 'content_hash' => hash('sha256', $payload), 'is_deleted' => 0],
            ['is_deleted']
        );

        (new PublishedTemplateRecorder($resource, $json))->record(' shoes ', ['en_GB' => 'Shoes']);
    }
}
