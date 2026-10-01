<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Snapshot;

use Ergonode\TemplateConsumer\Api\TemplateSnapshotRemoverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class TemplateSnapshotRemover implements TemplateSnapshotRemoverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function remove(string $templateCode): void
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            throw new LocalizedException(__('Ergonode template code is required.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $templateTable = $this->resourceConnection->getTableName('ergonode_template');
        $connection->beginTransaction();
        try {
            $template = $connection->fetchRow(
                $connection->select()
                    ->from($templateTable, ['entity_id'])
                    ->where('code = ?', $templateCode)
                    ->limit(1)
                    ->forUpdate(true)
            );
            if (!is_array($template)) {
                throw new LocalizedException(
                    __('Template "%1" is not available in the local snapshot.', $templateCode)
                );
            }
            $connection->delete($templateTable, ['entity_id = ?' => (int)$template['entity_id']]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
