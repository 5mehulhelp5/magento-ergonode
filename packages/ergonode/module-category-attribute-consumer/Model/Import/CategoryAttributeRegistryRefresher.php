<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Import;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeRegistryRefresherInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class CategoryAttributeRegistryRefresher implements CategoryAttributeRegistryRefresherInterface
{
    public function __construct(
        private readonly AttributeDefinitionSynchronizationInterface $definitionSynchronization,
        private readonly CategoryAttributeCodeLoaderInterface $categoryAttributeCodeLoader,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function refresh(): array
    {
        $this->definitionSynchronization->synchronize();

        $codes = $this->categoryAttributeCodeLoader->load();

        $this->replaceRegistry($codes);

        return [
            'imported' => count($codes),
        ];
    }

    /** @param string[] $codes */
    private function replaceRegistry(array $codes): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_attribute');

        $connection->beginTransaction();
        try {
            $connection->delete($table);
            if ($codes !== []) {
                $connection->insertArray($table, ['attribute_code'], array_map(
                    static fn (string $code): array => [$code],
                    $codes
                ));
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
