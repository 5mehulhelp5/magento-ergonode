<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Config;

use Ergonode\Core\Model\Config\EncryptedConnectionMode;

class ConnectionMode extends EncryptedConnectionMode
{
    public const string CODE = 'write';
    public const string XML_PATH_TEST_API_KEY = 'ergonode_connection/test/publisher/api_key';
    public const string XML_PATH_PRODUCTION_API_KEY = 'ergonode_connection/production/publisher/api_key';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'Read and write';
    }

    public function allowsWrites(): bool
    {
        return true;
    }

    protected function getApiKeyPaths(): array
    {
        return [
            'test' => self::XML_PATH_TEST_API_KEY,
            'production' => self::XML_PATH_PRODUCTION_API_KEY,
        ];
    }
}
