<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\ResourceModel;

use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionCheckRecorderInterface;
use Ergonode\Core\Api\SynchronizationObservationProviderInterface;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;
use Throwable;

class AttributeDefinitionCheck implements
    AttributeDefinitionCheckRecorderInterface,
    SynchronizationObservationProviderInterface
{
    public const string FLAG_CODE = 'ergonode_attribute_definition_check';

    public function __construct(
        private readonly FlagManager $flagManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @return array{status: string, started_at: ?string, completed_at: ?string, changed_at: ?string} */
    public function getStatus(): array
    {
        $data = $this->flagManager->getFlagData(self::FLAG_CODE);
        $data = is_array($data) ? $data : [];

        return [
            'status' => is_string($data['status'] ?? null) ? $data['status'] : 'not_checked',
            'started_at' => is_string($data['started_at'] ?? null) ? $data['started_at'] : null,
            'completed_at' => is_string($data['completed_at'] ?? null) ? $data['completed_at'] : null,
            'changed_at' => is_string($data['changed_at'] ?? null) ? $data['changed_at'] : null,
        ];
    }

    public function record(string $status): void
    {
        try {
            $data = $this->getStatus();
            $now = gmdate('Y-m-d H:i:s');
            $data['status'] = $status;
            if ($status === 'running') {
                $data['started_at'] = $now;
                $data['completed_at'] = null;
            } elseif ($status !== 'changes_detected') {
                $data['completed_at'] = $now;
            }
            if ($status === 'changes_detected') {
                $data['changed_at'] = $now;
            }
            $this->flagManager->saveFlag(self::FLAG_CODE, $data);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to record the attribute definition check.', ['exception' => $exception]);
        }
    }
}
