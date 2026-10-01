<?php

declare(strict_types=1);

namespace Ergonode\ConsumerAdminUi\Model\Config\Backend;

use Ergonode\CoreAdminUi\Model\Config\Backend\RequiredApiKey;
use Ergonode\Consumer\Model\Config\ConnectionMode;

class ApiKey extends RequiredApiKey
{
    protected function getModeCode(): string
    {
        return ConnectionMode::CODE;
    }
}
