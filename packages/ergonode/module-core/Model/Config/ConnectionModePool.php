<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Config;

use Ergonode\Core\Api\ConnectionModeInterface;
use Magento\Framework\Exception\LocalizedException;

class ConnectionModePool
{
    /**
     * @param array<string, ConnectionModeInterface> $modes
     */
    public function __construct(private readonly array $modes = [])
    {
    }

    /**
     * @return array<string, ConnectionModeInterface>
     */
    public function getModes(): array
    {
        $modes = $this->modes;
        ksort($modes);

        return $modes;
    }

    public function has(string $code): bool
    {
        return isset($this->modes[$code]);
    }

    public function get(string $code): ConnectionModeInterface
    {
        if (!$this->has($code)) {
            throw new LocalizedException(__('Choose an available Ergonode operating mode.'));
        }

        return $this->modes[$code];
    }
}
