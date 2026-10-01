<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumerAdminUi\Model\Mapping;

use Ergonode\Template\Api\MappingTargetResolverInterface;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Magento\Framework\Exception\LocalizedException;

class CreationTargetResolver implements MappingTargetResolverInterface
{
    public function __construct(
        private readonly TemplateConfigProvider $configProvider,
        private readonly AttributeSetManager $attributeSetManager
    ) {
    }

    public function resolve(string $templateCode, int|string $target): ?int
    {
        if ($target !== '__create_magento_attribute_set__') {
            return null;
        }
        if (!$this->configProvider->shouldCreateAttributeSets()) {
            throw new LocalizedException(__('Magento attribute set creation is disabled in Ergonode configuration.'));
        }

        return (int)$this->attributeSetManager->createOrGetForTemplate($templateCode)['attribute_set_id'];
    }
}
