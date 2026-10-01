<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Config;

use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Config\CategoryNameTargetProvider as DefaultTargetProvider;
use Ergonode\CategoryAttributeConsumer\Model\Provider\CategoryNameAttributeProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryNameTargetProvider implements CategoryNameTargetProviderInterface
{
    public const string XML_PATH_ATTRIBUTE = 'ergonode_categories/synchronization/name_attribute';

    public function __construct(
        private readonly CategoryConfigProvider $configProvider,
        private readonly DefaultTargetProvider $defaultProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CategoryNameAttributeProvider $attributeProvider
    ) {
    }

    public function getAttributeCode(): ?string
    {
        if ($this->configProvider->getNameMode() !== 'attribute') {
            return $this->defaultProvider->getAttributeCode();
        }
        $code = (string)$this->scopeConfig->getValue(self::XML_PATH_ATTRIBUTE);
        if (!isset($this->attributeProvider->getAttributes()[$code])) {
            throw new LocalizedException(__('Choose a text field for the category name.'));
        }

        return $code;
    }
}
