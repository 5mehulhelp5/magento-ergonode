<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Config;

use Ergonode\ProductAttributePublisher\Api\AttributeSourceConfigurationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeSourceConfiguration implements AttributeSourceConfigurationInterface
{
    private const string XML_PATH_BASE_CURRENCY = 'currency/options/base';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getPriceCurrency(): string
    {
        $value = trim((string)$this->scopeConfig->getValue(self::XML_PATH_BASE_CURRENCY));
        if ($value === '') {
            throw new LocalizedException(__(
                'Magento base currency is not configured at "%1".',
                self::XML_PATH_BASE_CURRENCY
            ));
        }

        return $value;
    }
}
