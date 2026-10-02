<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ResourceModel;

use Ergonode\Media\Api\GalleryModeLockInterface;
use Ergonode\Media\Model\Config\MediaConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class GalleryModeLock implements GalleryModeLockInterface
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function getLockedMode(): ?string
    {
        $value = $this->resource->getConnection()->fetchOne($this->resource->getConnection()->select()
            ->from($this->resource->getTableName('ergonode_media_state'), ['gallery_mode'])
            ->where('state_id = ?', 1)->limit(1));
        return $value === false ? null : (string)$value;
    }

    public function lock(string $mode): void
    {
        if (!in_array($mode, [MediaConfig::MODE_SHARED, MediaConfig::MODE_SEO], true)) {
            throw new LocalizedException(__('Invalid Ergonode gallery mode.'));
        }
        $this->resource->getConnection()->insertOnDuplicate($this->resource->getTableName('ergonode_media_state'), [[
            'state_id' => 1, 'gallery_mode' => $mode,
        ]], []);
        if ($this->getLockedMode() !== $mode) {
            throw new LocalizedException(__('Ergonode gallery mode is already locked to another value.'));
        }
    }
}
