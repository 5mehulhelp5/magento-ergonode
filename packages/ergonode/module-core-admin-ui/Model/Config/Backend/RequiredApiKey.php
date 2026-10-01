<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Backend;

use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\Exception\LocalizedException;

abstract class RequiredApiKey extends Encrypted
{
    public function beforeSave()
    {
        if ($this->isActiveConnection() && $this->getEffectiveApiKey() === '') {
            throw new LocalizedException(__('API key is required for the active environment and operating mode.'));
        }

        parent::beforeSave();

        return $this;
    }

    abstract protected function getModeCode(): string;

    private function isActiveConnection(): bool
    {
        $fields = $this->getData('groups/general/fields') ?? [];
        $environment = $fields['environment']['value']
            ?? $this->_config->getValue(ConfigProvider::XML_PATH_ENVIRONMENT);
        $mode = $fields['mode']['value'] ?? $this->_config->getValue(ConfigProvider::XML_PATH_MODE);
        $path = explode('/', (string)$this->getPath());
        $fieldEnvironment = $path[1] ?? '';
        $enabled = $this->getData('groups/general/fields/enabled/value')
            ?? $this->_config->getValue(ConfigProvider::XML_PATH_ENABLED);

        return (bool)$enabled && $fieldEnvironment === $environment && $mode === $this->getModeCode();
    }

    private function getEffectiveApiKey(): string
    {
        $value = trim((string)$this->getValue());
        if ($value === '' || !preg_match('/^\*+$/', $value)) {
            return $value;
        }
        $oldValue = (string)$this->getOldValue();

        return $oldValue !== '' ? trim((string)$this->_encryptor->decrypt($oldValue)) : '';
    }
}
