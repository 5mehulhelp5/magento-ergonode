<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Backend;

use Ergonode\Media\Api\GalleryAttributeOptionsInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class GalleryAttribute extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly GalleryAttributeOptionsInterface $attributes,
        ?AbstractResource $resource = null,
        ?AbstractDb $collection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $collection, $data);
    }
    public function beforeSave(): self
    {
        $code = trim((string)$this->getValue());
        if ($code === '' || $this->getData('scope') !== 'default') {
            throw new LocalizedException(__('Choose a global gallery attribute.'));
        }
        if (!array_key_exists($code, $this->attributes->getOptions())) {
            throw new LocalizedException(__('The selected attribute is not an available Ergonode Gallery attribute.'));
        }
        $this->setValue($code);

        return parent::beforeSave();
    }
}
