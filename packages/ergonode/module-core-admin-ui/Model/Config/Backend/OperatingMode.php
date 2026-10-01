<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Backend;

use Ergonode\Core\Model\Config\ConnectionModePool;
use Magento\Config\Model\ResourceModel\Config\Data as ConfigResource;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

class OperatingMode extends Value
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ConnectionModePool $modePool,
        ?ConfigResource $resource = null,
        ?ConfigCollection $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $this->modePool->get((string)$this->getValue());

        return parent::beforeSave();
    }
}
