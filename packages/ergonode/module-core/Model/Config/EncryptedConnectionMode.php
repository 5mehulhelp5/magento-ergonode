<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Config;

use Ergonode\Core\Api\ConnectionModeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;

abstract class EncryptedConnectionMode implements ConnectionModeInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function getApiKey(string $environment): string
    {
        $paths = $this->getApiKeyPaths();
        if (!isset($paths[$environment])) {
            throw new LocalizedException(__('Choose a valid Ergonode environment.'));
        }
        $value = (string)$this->scopeConfig->getValue($paths[$environment]);

        return $value !== '' ? trim((string)$this->encryptor->decrypt($value)) : '';
    }

    /**
     * @return array<string, string>
     */
    abstract protected function getApiKeyPaths(): array;
}
