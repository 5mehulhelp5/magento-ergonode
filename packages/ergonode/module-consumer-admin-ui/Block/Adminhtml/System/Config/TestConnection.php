<?php

declare(strict_types=1);

namespace Ergonode\ConsumerAdminUi\Block\Adminhtml\System\Config;

use Ergonode\CoreAdminUi\Block\Adminhtml\System\Config\TestConnection as ConnectionButton;
use Ergonode\Consumer\Model\Config\ConnectionMode;

class TestConnection extends ConnectionButton
{
    protected function getModeCode(): string
    {
        return ConnectionMode::CODE;
    }
}
