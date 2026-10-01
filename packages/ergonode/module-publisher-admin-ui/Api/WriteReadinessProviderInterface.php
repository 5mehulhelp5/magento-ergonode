<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Api;

interface WriteReadinessProviderInterface
{
    /** @return array{ready: bool, message: string, configuration_url: string} */
    public function getStatus(): array;
}
