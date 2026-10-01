<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Model;

use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProviderFactory;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProviderFactory;
use Magento\Eav\Api\AttributeOptionManagementInterfaceFactory;
use Magento\Eav\Model\AttributeRepositoryFactory;
use Magento\Eav\Model\ConfigFactory;

class MagentoOptionsFactory
{
    public function __construct(
        private readonly MagentoOptionProviderFactory $optionFactory,
        private readonly MagentoAttributeProviderFactory $attributeFactory,
        private readonly AttributeOptionManagementInterfaceFactory $managementFactory,
        private readonly AttributeRepositoryFactory $repositoryFactory,
        private readonly ConfigFactory $configFactory
    ) {
    }

    public function create(): MagentoOptionProvider
    {
        // EAV retains option/source objects within a request. Isolate both snapshots
        // without clearing the application's shared metadata or changing live data.
        $repository = $this->repositoryFactory->create(['eavConfig' => $this->configFactory->create()]);
        return $this->optionFactory->create([
            'magentoAttributeProvider' => $this->attributeFactory->create(),
            'attributeOptionManagement' => $this->managementFactory->create(['attributeRepository' => $repository]),
        ]);
    }
}
