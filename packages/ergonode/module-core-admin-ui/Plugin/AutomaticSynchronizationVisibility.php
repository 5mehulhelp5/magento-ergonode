<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Plugin;

use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Config\Model\Config\Structure\AbstractElement;

class AutomaticSynchronizationVisibility
{
    /** @param list<string> $paths Configuration paths owned and contributed by domain Admin modules. */
    public function __construct(private readonly ConfigProvider $config, private readonly array $paths = [])
    {
    }
    public function afterIsVisible(AbstractElement $subject, bool $result): bool
    {
        return $result && ($this->config->getMode() === 'read' || !in_array($subject->getPath(), $this->paths, true));
    }
}
