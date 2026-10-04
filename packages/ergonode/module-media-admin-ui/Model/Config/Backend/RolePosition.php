<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Backend;

use Ergonode\ProductMedia\Api\ImageRulesNormalizerInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;

class RolePosition extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ImageRulesNormalizerInterface $normalizer,
        ?AbstractResource $resource = null,
        ?AbstractDb $collection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $collection, $data);
    }

    public function beforeSave(): self
    {
        if ($this->getData('scope') !== 'default') {
            throw new LocalizedException(__('Choose a global image role position between 2 and 65535.'));
        }
        $this->setValue($this->normalizer->normalizePosition($this->getValue()));
        return parent::beforeSave();
    }
}
