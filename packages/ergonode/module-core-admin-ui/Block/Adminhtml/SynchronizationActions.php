<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml;

use Magento\Backend\Block\Template;

class SynchronizationActions extends Template
{
    /** @var string */
    protected $_template = 'Ergonode_CoreAdminUi::synchronization/actions.phtml';

    public function getPrimaryRole(): string
    {
        return trim((string)$this->getData('primary_role')) ?: 'sync-ergonode';
    }

    public function getPrimaryTitle(): string
    {
        return trim((string)$this->getData('primary_title')) ?: (string)__('Synchronize with Magento');
    }

    public function hasCursorActions(): bool
    {
        return (bool)$this->getData('has_cursor_actions');
    }

    public function isPrimaryDisabled(): bool
    {
        return (bool)$this->getData('primary_disabled');
    }

    public function isCursorResetDisabled(): bool
    {
        return (bool)$this->getData('cursor_reset_disabled');
    }
}
