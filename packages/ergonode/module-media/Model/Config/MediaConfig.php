<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class MediaConfig
{
    public const string MODE_SHARED = 'shared';
    public const string MODE_SEO = 'seo';
    public const string XML_PATH_GALLERY_MODE = 'ergonode_products/media/gallery_mode';
    public const string XML_PATH_STREAM_PAGE_SIZE = 'ergonode_products/media/stream_page_size';
    public const string XML_PATH_BATCH_SIZE = 'ergonode_products/media/consumer_batch_size';
    public const string XML_PATH_LEASE_SECONDS = 'ergonode_products/media/lease_seconds';
    public function __construct(private readonly ScopeConfigInterface $config)
    {
    }
    public function getMode(): string
    {
        $v = strtolower(trim((string)$this->config->getValue(self::XML_PATH_GALLERY_MODE)));
        return in_array($v, [self::MODE_SHARED, self::MODE_SEO], true) ? $v : self::MODE_SHARED;
    }

    public function getStreamPageSize(): int
    {
        return max(1, min(200, (int)$this->config->getValue(self::XML_PATH_STREAM_PAGE_SIZE)));
    }
    public function getConsumerBatchSize(): int
    {
        return max(1, min(50, (int)$this->config->getValue(self::XML_PATH_BATCH_SIZE)));
    }
    public function getLeaseSeconds(): int
    {
        return max(60, min(3600, (int)$this->config->getValue(self::XML_PATH_LEASE_SECONDS)));
    }
}
