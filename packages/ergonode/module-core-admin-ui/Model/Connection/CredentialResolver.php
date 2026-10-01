<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Connection;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Config\ConnectionModePool;
use Magento\Framework\Exception\LocalizedException;

class CredentialResolver
{
    public function __construct(private readonly ConnectionModePool $modePool)
    {
    }

    public function resolveApiKey(string $mode, string $environment, string $submittedApiKey): string
    {
        $provider = $this->modePool->get($mode);
        if (!in_array($environment, [
            ConfigProvider::ENVIRONMENT_TEST,
            ConfigProvider::ENVIRONMENT_PRODUCTION,
        ], true)) {
            throw new LocalizedException(__('Choose a valid Ergonode environment.'));
        }
        $submittedApiKey = trim($submittedApiKey);

        return preg_match('/^\*+$/', $submittedApiKey)
            ? $provider->getApiKey($environment)
            : $submittedApiKey;
    }
}
