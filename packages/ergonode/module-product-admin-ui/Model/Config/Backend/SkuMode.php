<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Model\Config\Backend;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class SkuMode extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave(): AbstractModel
    {
        $this->identityModeProvider->assertModeSupported((string)$this->getValue());

        return parent::beforeSave();
    }
}
