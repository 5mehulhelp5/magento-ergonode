<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerHistory\Test\Integration\Model;

use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeHistory\Api\HistoryQueryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class InboundHistoryTest extends TestCase
{
    public function testInboundManualCreationIsIncludedInSingleSaveHistory(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $code = 'history_created_attr';
        $resource->getConnection()->insert($resource->getTableName('ergonode_attribute'), [
            'code' => $code,
            'type' => 'text',
            'scope' => 'global',
            'labels_json' => '{"en_US":"History created attribute"}',
            'parameters_json' => '{}',
            'content_hash' => hash('sha256', $code),
        ]);
        $query = $objects->get(HistoryQueryInterface::class);
        $before = $query->getOperations()['total'];

        $objects->get(AttributeMappingSaver::class)->save([[
            'left' => ['code' => $code],
            'right' => ['code' => 'pending_' . $code, 'pending_create' => true],
        ]], []);

        $operations = $query->getOperations(1);
        self::assertSame($before + 1, $operations['total']);
        $operationId = $operations['items'][0]['operation_id'];
        $state = $query->getState($operationId);
        $targets = array_column($state['target'], null, 'code');
        self::assertSame($code, $targets[$code]['mapped_code']);
        $changes = $state['changes'];
        self::assertNotEmpty(array_filter(
            $changes,
            static fn (array $change): bool => $change['side'] === 'target'
                && $change['code'] === $code && in_array('created', $change['actions'], true)
        ));
    }
}
