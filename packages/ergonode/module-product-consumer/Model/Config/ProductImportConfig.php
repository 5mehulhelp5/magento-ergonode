<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ProductImportConfig
{
    private const string PREFIX = 'ergonode_products/import/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'enabled');
    }

    public function getStreamPageSize(): int
    {
        return $this->boundedInt('stream_page_size', 25, 1, 50);
    }

    public function getConsumerBatchSize(): int
    {
        return $this->boundedInt('consumer_batch_size', 25, 1, 50);
    }

    public function getMaximumPendingItems(): int
    {
        return $this->boundedInt('maximum_pending_items', 10000, 100, 100000);
    }

    public function getMaximumAttempts(): int
    {
        return $this->boundedInt('maximum_attempts', 8, 1, 100);
    }

    public function getLeaseSeconds(): int
    {
        return $this->boundedInt('lease_seconds', 900, 60, 3600);
    }

    public function getTypeAttributeCode(): string
    {
        $code = trim((string)$this->scopeConfig->getValue(self::PREFIX . 'type_attribute_code'));

        return preg_match('/^[a-z][a-z0-9_]{0,254}$/', $code) === 1 ? $code : 'magento_product_type';
    }

    public function getActiveStatusCode(): string
    {
        $code = trim((string)$this->scopeConfig->getValue(self::PREFIX . 'active_status_code'));

        return $code !== '' ? $code : 'active';
    }

    private function boundedInt(string $field, int $default, int $minimum, int $maximum): int
    {
        $value = (int)$this->scopeConfig->getValue(self::PREFIX . $field);
        if ($value < $minimum || $value > $maximum) {
            return $default;
        }

        return $value;
    }
}
