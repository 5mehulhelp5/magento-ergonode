<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Api;

interface ManualPlacementSaverInterface
{
    /**
     * @param string $templateCode
     * @param int $attributeSetId
     * @param int $attributeId
     * @param bool $manual
     * @return void
     */
    public function save(string $templateCode, int $attributeSetId, int $attributeId, bool $manual): void;
}
