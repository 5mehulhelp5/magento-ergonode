<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Model\Config\Backend;

use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class MagentoAttribute extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly MagentoIdentityAttributeInterface $identityAttribute,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave(): AbstractModel
    {
        $code = trim((string)$this->getValue());
        if ($code !== '') {
            $this->identityAttribute->validateCode($code);
        }
        $this->setValue($code);

        return parent::beforeSave();
    }
}
