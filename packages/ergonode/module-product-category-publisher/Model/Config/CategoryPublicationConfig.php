<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class CategoryPublicationConfig
{
    public const string MODE_KEEP = 'keep';
    public const string MODE_MATCH = 'match';

    private const string XML_PATH_MODE = 'ergonode_products/publication/category_mode';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getMode(): string
    {
        $mode = trim((string)$this->scopeConfig->getValue(self::XML_PATH_MODE));

        return $mode === self::MODE_MATCH ? self::MODE_MATCH : self::MODE_KEEP;
    }

    public function shouldRemoveMissingCategories(): bool
    {
        return $this->getMode() === self::MODE_MATCH;
    }
}
