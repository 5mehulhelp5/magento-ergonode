<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml;

use Ergonode\CoreAdminUi\Model\WorkspaceAvailability;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class ConnectionNotice extends Template
{
    private const string CONFIGURATION_RESOURCE = 'Ergonode_Core::config';

    /** @var string[]|null */
    private ?array $problems = null;

    public function __construct(
        Context $context,
        private readonly WorkspaceAvailability $availability,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return string[] */
    public function getProblems(): array
    {
        return $this->problems ??= $this->availability->getProblems();
    }

    public function getConfigurationUrl(): string
    {
        return $this->getAuthorization()->isAllowed(self::CONFIGURATION_RESOURCE)
            ? $this->getUrl('adminhtml/system_config/edit', ['section' => 'ergonode_connection'])
            : '';
    }
}
