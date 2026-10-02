<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Backend;

use Ergonode\ProductMedia\Api\AdditionalRoleOptionsInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class AdditionalRole extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly AdditionalRoleOptionsInterface $attributes,
        ?AbstractResource $resource = null,
        ?AbstractDb $collection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $collection, $data);
    }
    public function beforeSave(): self
    {
        $code = trim((string)$this->getValue());
        if ($this->getData('scope') !== 'default') {
            throw new LocalizedException(__('Image roles must be configured globally.'));
        }
        if ($code !== '' && !array_key_exists($code, $this->attributes->getOptions())) {
            throw new LocalizedException(__('Choose one available additional Magento image role.'));
        }
        $this->setValue($code);

        return parent::beforeSave();
    }
}
