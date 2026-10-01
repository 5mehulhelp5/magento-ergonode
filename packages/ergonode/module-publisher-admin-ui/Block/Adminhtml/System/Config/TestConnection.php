<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Block\Adminhtml\System\Config;

use Ergonode\CoreAdminUi\Block\Adminhtml\System\Config\TestConnection as ConnectionButton;
use Ergonode\Publisher\Model\Config\ConnectionMode;

class TestConnection extends ConnectionButton
{
    protected function getModeCode(): string
    {
        return ConnectionMode::CODE;
    }
}
