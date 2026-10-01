<?php

declare(strict_types=1);

namespace Ergonode\Template\Api;

interface TemplateAttributeSetResolverInterface
{
    /**
     * Return the active Magento attribute-set mapping for an Ergonode template.
     *
     * @param string $templateCode
     * @return int|null
     */
    public function resolveAttributeSetId(string $templateCode): ?int;
}
