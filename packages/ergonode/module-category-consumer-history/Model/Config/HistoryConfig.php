<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class HistoryConfig
{
    private const string CONFIG_PATH = 'ergonode_categories/history/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH . 'enabled');
    }

    public function isCleanupEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH . 'cleanup_enabled');
    }

    public function getRetentionDays(): ?int
    {
        $days = filter_var(
            $this->scopeConfig->getValue(self::CONFIG_PATH . 'retention_days'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $days !== false ? $days : null;
    }
}
