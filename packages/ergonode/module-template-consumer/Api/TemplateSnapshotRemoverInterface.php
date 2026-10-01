<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface TemplateSnapshotRemoverInterface
{
    /**
     * @param string $templateCode
     * @return void
     * @throws LocalizedException
     */
    public function remove(string $templateCode): void;
}
