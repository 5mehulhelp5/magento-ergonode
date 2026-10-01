<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Http;

use Ergonode\Core\Api\DownloadSourcePolicyInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Framework\Exception\LocalizedException;

class DownloadSourcePolicy implements DownloadSourcePolicyInterface
{
    public function __construct(
        private readonly ConfigProvider $config
    ) {
    }

    public function authorize(string $url): void
    {
        $target = parse_url($url);
        if (!is_array($target) || !isset($target['scheme'], $target['host'])
            || isset($target['user']) || isset($target['pass'])
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || !in_array(strtolower($target['scheme']), ['http', 'https'], true)
        ) {
            throw new LocalizedException(__('Invalid Ergonode download URL.'));
        }
        $origin = parse_url($this->config->getGraphQlUrl());
        if (is_array($origin) && isset($origin['scheme'], $origin['host'])
            && $this->origin($target) === $this->origin($origin)
        ) {
            return;
        }
        throw new LocalizedException(__('The file download URL must use the configured Ergonode origin.'));
    }

    /** @param array{scheme: string, host: string, port?: int} $parts */
    private function origin(array $parts): string
    {
        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        return $scheme . '://' . strtolower($parts['host']) . ':' . $port;
    }
}
