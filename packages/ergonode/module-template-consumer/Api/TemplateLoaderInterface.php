<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface TemplateLoaderInterface
{
    /**
     * @param string $templateCode
     * @return array{code: string, name: array<int, array<string, mixed>>}
     * @throws LocalizedException
     */
    public function load(string $templateCode): array;
}
