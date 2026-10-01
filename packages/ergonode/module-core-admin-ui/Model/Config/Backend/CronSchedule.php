<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Backend;

use Ergonode\CoreAdminUi\Model\Config\CronExpressionValidator;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class CronSchedule extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly CronExpressionValidator $cronExpressionValidator,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave(): AbstractModel
    {
        $this->setValue($this->cronExpressionValidator->validate((string)$this->getValue()));

        return parent::beforeSave();
    }
}
