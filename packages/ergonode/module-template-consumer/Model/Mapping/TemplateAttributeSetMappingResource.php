<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Mapping;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class TemplateAttributeSetMappingResource
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function assign(string $templateCode, int $attributeSetId): void
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            $template = $connection->fetchRow(
                $connection->select()
                    ->from($this->templateTable(), ['entity_id'])
                    ->where('code = ?', $templateCode)
                    ->limit(1)
                    ->forUpdate(true)
            );
            if (!is_array($template)) {
                throw new LocalizedException(__('Ergonode template "%1" is not imported yet.', $templateCode));
            }

            $owner = $this->findOwner($attributeSetId, true);
            if ($owner !== null && $owner !== $templateCode) {
                throw $this->conflict($attributeSetId, $owner);
            }

            try {
                $connection->update(
                    $this->templateTable(),
                    ['attribute_set_id' => $attributeSetId],
                    ['entity_id = ?' => (int)$template['entity_id']]
                );
            } catch (Throwable $exception) {
                $owner = $this->findOwner($attributeSetId, false);
                if ($owner !== null && $owner !== $templateCode) {
                    throw $this->conflict($attributeSetId, $owner);
                }

                throw $exception;
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function findOwner(int $attributeSetId, bool $forUpdate): ?string
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->templateTable(), ['code'])
            ->where('attribute_set_id = ?', $attributeSetId)
            ->limit(1);
        if ($forUpdate) {
            $select->forUpdate(true);
        }
        $owner = $this->getConnection()->fetchOne($select);

        return $owner !== false ? (string)$owner : null;
    }

    private function conflict(int $attributeSetId, string $owner): LocalizedException
    {
        return new LocalizedException(__(
            'Magento attribute set "%1" is already mapped to Ergonode template "%2".',
            $attributeSetId,
            $owner
        ));
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function templateTable(): string
    {
        return $this->resourceConnection->getTableName('ergonode_template');
    }
}
