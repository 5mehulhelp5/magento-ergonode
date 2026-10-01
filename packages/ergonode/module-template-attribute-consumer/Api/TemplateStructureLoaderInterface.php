<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface TemplateStructureLoaderInterface
{
    /**
     * @param string $templateCode
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function load(string $templateCode): array;
}
