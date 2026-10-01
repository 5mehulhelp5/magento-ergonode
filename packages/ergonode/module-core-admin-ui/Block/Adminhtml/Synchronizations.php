<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml;

use Ergonode\Core\Api\SynchronizationOperationExecutorInterface;
use Ergonode\Core\Api\SynchronizationStatusProviderInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Synchronizations extends Template
{
    private const string ACL_RUN = 'Ergonode_Core::synchronizations_run';
    private const string ACL_RESET = 'Ergonode_Core::synchronizations_reset';

    public function __construct(
        Context $context,
        private readonly SynchronizationStatusProviderInterface $statusProvider,
        private readonly SynchronizationOperationExecutorInterface $operationExecutor,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return list<array{
     *     process_code: string,
     *     label: string,
     *     description: string,
     *     cursor: string|null,
     *     synced_at: string|null
     * }>
     */
    public function getSynchronizations(): array
    {
        return $this->statusProvider->getList();
    }

    public function formatProcessCode(string $processCode): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $processCode))));
    }

    public function isProcessActionable(string $processCode): bool
    {
        return $this->operationExecutor->isAvailable($processCode);
    }

    public function canRunSynchronizations(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ACL_RUN);
    }

    public function canResetSynchronizationCursors(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ACL_RESET);
    }

    public function getSynchronizationConfigJson(): string
    {
        return $this->json->serialize([
            'form_key' => $this->getFormKey(),
            'urls' => [
                'status' => $this->getUrl('ergonode/synchronization/status'),
                'run' => $this->getUrl('ergonode/synchronization/run'),
                'reset' => $this->getUrl('ergonode/synchronization/reset'),
            ],
        ]);
    }
}
