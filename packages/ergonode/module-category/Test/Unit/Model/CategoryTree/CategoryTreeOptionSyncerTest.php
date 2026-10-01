<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\CategoryTree;

use Ergonode\Category\Model\CategoryTree\CategoryTreeOptionSyncer;
use Ergonode\Core\Model\GraphQl\Client;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryTreeOptionSyncerTest extends TestCase
{
    public function testSyncRejectsAnEmptyRemoteTreeList(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('query')->willReturn(['categoryTreeStream' => ['edges' => []]]);
        $syncer = new CategoryTreeOptionSyncer(
            $client,
            $this->createStub(ResourceConnection::class),
            new Json()
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode did not return any category trees.');

        $syncer->sync();
    }
}
