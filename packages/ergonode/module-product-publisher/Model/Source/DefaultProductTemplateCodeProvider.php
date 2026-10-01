<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Api\ProductTemplateCodeProviderInterface;

class DefaultProductTemplateCodeProvider implements ProductTemplateCodeProviderInterface
{
    private const string DEFAULT_TEMPLATE_CODE = 'default';

    public function getTemplateCodesByAttributeSetIds(array $attributeSetIds): array
    {
        $result = [];
        foreach ($attributeSetIds as $attributeSetId) {
            $attributeSetId = (int)$attributeSetId;
            if ($attributeSetId > 0) {
                $result[$attributeSetId] = self::DEFAULT_TEMPLATE_CODE;
            }
        }
        ksort($result);

        return $result;
    }
}
