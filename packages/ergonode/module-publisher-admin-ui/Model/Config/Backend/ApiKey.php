<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Model\Config\Backend;

use Ergonode\CoreAdminUi\Model\Config\Backend\RequiredApiKey;
use Ergonode\Publisher\Model\Config\ConnectionMode;

class ApiKey extends RequiredApiKey
{
    protected function getModeCode(): string
    {
        return ConnectionMode::CODE;
    }
}
