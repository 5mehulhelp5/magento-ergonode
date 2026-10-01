<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisherAdminUi\Test\E2e;

use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Ergonode\TemplatePublisher\Test\E2e\Support\RemoteTemplateFixture;
use Ergonode\TemplatePublisherAdminUi\Model\TemplateFromAttributeSetCreator;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

class LiveTemplateRecordingTest extends TestCase
{
    public function testRemoteCreationIsRecordedAndBothFixturesAreRemoved(): void
    {
        $manager = Bootstrap::create(dirname(__DIR__, 5), $_SERVER)->getObjectManager();
        $config = $manager->get(ConfigProvider::class);
        self::assertSame('test', $config->getEnvironment(), 'Live template fixtures require the test tenant.');
        self::assertTrue($config->allowsWrites(), 'Live template fixtures require the write connection mode.');

        $attributeSets = $manager->get(ProductAttributeSetProviderInterface::class)->getProductAttributeSets();
        self::assertNotEmpty($attributeSets, 'At least one Magento product attribute set is required.');
        $attributeSetId = (int)$attributeSets[0]['id'];
        $fixture = new RemoteTemplateFixture(
            $manager->get(GraphQlWriteScopeQueryClientInterface::class),
            $manager->get(GraphQlMutationClientInterface::class)
        );
        $code = $fixture->uniqueCode('recording');
        $resource = $manager->get(ResourceConnection::class);
        $database = $resource->getConnection();
        $table = $resource->getTableName('ergonode_template');

        self::assertNull($fixture->find($code), 'The unique remote fixture code must be unused before creation.');
        self::assertSame(0, (int)$database->fetchOne(
            $database->select()->from($table, ['COUNT(*)'])->where('code = ?', $code)
        ));
        try {
            $result = $manager->get(TemplateFromAttributeSetCreator::class)->create($code, $attributeSetId);

            self::assertSame($code, $result['code']);
            self::assertSame($code, $fixture->requireVisible($code)['code'] ?? null);
            $record = $database->fetchRow(
                $database->select()->from($table)->where('code = ?', $code)
            );
            self::assertIsArray($record);
            self::assertSame(0, (int)$record['is_deleted']);
            self::assertStringContainsString($code, (string)$record['raw_json']);
        } finally {
            try {
                $database->delete($table, ['code = ?' => $code]);
            } finally {
                $fixture->removeAndVerify($code);
            }
        }

        self::assertSame(0, (int)$database->fetchOne(
            $database->select()->from($table, ['COUNT(*)'])->where('code = ?', $code)
        ));
    }
}
