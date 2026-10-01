<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Backend;

use Magento\Config\Model\ResourceModel\Config\Data as ConfigResource;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Ergonode\CoreAdminUi\Model\Connection\EndpointResolver;
use Ergonode\Core\Model\Config\ConfigProvider;

class ErgonodeUrl extends Value
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly EndpointResolver $endpointResolver,
        ?ConfigResource $resource = null,
        ?ConfigCollection $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = trim((string)$this->getValue());
        $environment = $this->getData('groups/general/fields/environment/value')
            ?? $this->_config->getValue(ConfigProvider::XML_PATH_ENVIRONMENT);
        $fieldEnvironment = explode('/', (string)$this->getPath())[1] ?? '';
        $enabled = $this->getData('groups/general/fields/enabled/value')
            ?? $this->_config->getValue(ConfigProvider::XML_PATH_ENABLED);
        if ($value !== '' || ((bool)$enabled && $fieldEnvironment === $environment)) {
            $this->endpointResolver->resolveGraphQlUrl($value);
        }
        $this->setValue($value);

        return parent::beforeSave();
    }
}
