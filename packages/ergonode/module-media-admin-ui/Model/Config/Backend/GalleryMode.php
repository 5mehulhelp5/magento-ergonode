<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Backend;

use Ergonode\Media\Api\GalleryModeLockInterface;
use Ergonode\Media\Model\Config\MediaConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class GalleryMode extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly GalleryModeLockInterface $lock,
        ?AbstractResource $resource = null,
        ?AbstractDb $collection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $collection, $data);
    }
    public function beforeSave(): self
    {
        $mode = strtolower(trim((string)$this->getValue()));
        if (!in_array($mode, [MediaConfig::MODE_SHARED, MediaConfig::MODE_SEO], true)) {
            throw new LocalizedException(__('Choose a valid gallery mode.'));
        }
        $locked = $this->lock->getLockedMode();
        if ($locked !== null && $locked !== $mode) {
            throw new LocalizedException(__('Gallery mode is locked to "%1".', $locked));
        }
        $this->setValue($mode);
        return parent::beforeSave();
    }
}
