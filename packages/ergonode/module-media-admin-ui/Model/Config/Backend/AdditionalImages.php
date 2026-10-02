<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Backend;

use Ergonode\MediaAdminUi\Model\Config\AdditionalImagesValue;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class AdditionalImages extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly AdditionalImagesValue $valueCodec,
        ?AbstractResource $resource = null,
        ?AbstractDb $collection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $collection, $data);
    }
    protected function _afterLoad(): self
    {
        $raw = $this->getValue();
        $this->setValue(is_string($raw) && $raw !== '' ? $this->valueCodec->decode($raw) : []);
        return parent::_afterLoad();
    }
    public function beforeSave(): self
    {
        if ($this->getData('scope') !== 'default') {
            throw new LocalizedException(__('Additional images must be configured globally.'));
        }
        $this->setValue($this->valueCodec->serialize((array)$this->getValue()));
        return parent::beforeSave();
    }
}
