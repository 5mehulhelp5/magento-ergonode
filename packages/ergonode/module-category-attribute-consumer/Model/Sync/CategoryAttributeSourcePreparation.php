<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeRegistryRefresherInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Provider\ErgonodeCategoryAttributeProvider;

class CategoryAttributeSourcePreparation
{
    private bool $prepared = false;

    public function __construct(
        private readonly CategoryAttributeConfigProvider $config,
        private readonly CategoryAttributeRegistryRefresherInterface $registry,
        private readonly ErgonodeCategoryAttributeProvider $provider
    ) {
    }

    public function prepare(): void
    {
        if (!$this->config->isAttributeSynchronizationEnabled()) {
            return;
        }
        $this->prepared = false;
        $this->registry->refresh();
        $this->provider->reset();
        $this->prepared = true;
    }

    public function ensurePrepared(): void
    {
        if (!$this->prepared) {
            $this->prepare();
        }
    }
}
