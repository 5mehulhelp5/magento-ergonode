<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Plugin\Adminhtml;

use Ergonode\CoreAdminUi\Model\WorkspaceAvailability;
use Magento\Framework\View\Layout;

class WorkspaceConnectionGate
{
    public function __construct(private readonly WorkspaceAvailability $availability)
    {
    }

    /** Replaces opted-in content before any workspace or extension initializer renders. */
    public function aroundRenderElement(
        Layout $subject,
        callable $proceed,
        string $name,
        bool $useCache = true
    ): string {
        if ($name === 'content') {
            $notice = $subject->getBlock('ergonode.connection.notice');
            if ($notice !== false && $this->availability->getProblems() !== []) {
                return $notice->toHtml();
            }
        }

        return $proceed($name, $useCache);
    }
}
