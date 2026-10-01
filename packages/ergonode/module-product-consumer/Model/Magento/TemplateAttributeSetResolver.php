<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\ProductConsumer\Exception\DependencyUnavailableException;
use Ergonode\Template\Api\TemplateAttributeSetResolverInterface;

class TemplateAttributeSetResolver
{
    public function __construct(private readonly TemplateAttributeSetResolverInterface $templateResolver)
    {
    }

    public function resolve(string $templateCode): int
    {
        $templateCode = trim($templateCode);
        $attributeSetId = $this->templateResolver->resolveAttributeSetId($templateCode);
        if ($attributeSetId === null || $attributeSetId <= 0) {
            throw new DependencyUnavailableException(__(
                'Ergonode template "%1" does not have one active Magento attribute-set mapping yet.',
                $templateCode
            ));
        }

        return $attributeSetId;
    }
}
