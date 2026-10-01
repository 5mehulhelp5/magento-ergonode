<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Ergonode\Core\Model\Config\UpdateConfigProvider;

class UpdateGuard
{
    public function __construct(
        private readonly UpdateConfigProvider $configProvider
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function authorize(): string
    {
        if (!$this->configProvider->isEnabled()) {
            throw new LocalizedException(__('Ergonode updates are disabled.'));
        }

        $apiKey = $this->configProvider->getApiKey();
        if ($apiKey === '') {
            throw new LocalizedException(__('Ergonode update API key is missing.'));
        }

        return $apiKey;
    }
}
