<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\File;

use Ergonode\Core\Api\DownloadSourcePolicyInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\HTTP\Client\CurlFactory;

class RemoteFileDownloader
{
    private const int MAX_REDIRECTS = 5;

    public function __construct(
        private readonly CurlFactory $clients,
        private readonly DownloadSourcePolicyInterface $policy,
        private readonly ConfigProvider $config,
        private readonly File $files
    ) {
    }

    /** Stream into a caller-owned temporary file. Never follow an unchecked redirect. */
    public function download(string $url, string $path): void
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('Ergonode integration is disabled.'));
        }
        $stream = $this->files->fileOpen($path, 'wb');
        if ($stream === false) {
            throw new LocalizedException(__('Unable to open the temporary download file.'));
        }
        try {
            for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; ++$redirects) {
                $this->policy->authorize($url);
                $client = $this->clients->create();
                $client->setHeaders(['Accept' => '*/*']);
                $client->addHeader('X-API-KEY', $this->config->getApiKey());
                $client->setTimeout(60);
                $client->setOptions([
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_WRITEFUNCTION => static fn ($handle, string $chunk): int => (int)fwrite($stream, $chunk),
                ]);
                $client->get($url);
                $status = (int)$client->getStatus();
                if ($status >= 200 && $status < 300) {
                    if (ftell($stream) === 0) {
                        throw new LocalizedException(__('Ergonode returned an empty file.'));
                    }
                    return;
                }
                $location = '';
                foreach ($client->getHeaders() as $name => $value) {
                    if (strcasecmp((string)$name, 'Location') === 0 && is_string($value)) {
                        $location = $value;
                    }
                }
                if (!in_array($status, [301, 302, 303, 307, 308], true) || $location === '') {
                    throw new LocalizedException(__('Unable to download Ergonode file with HTTP status %1.', $status));
                }
                $url = (string)UriResolver::resolve(new Uri($url), new Uri($location));
                if (!ftruncate($stream, 0) || !rewind($stream)) {
                    throw new LocalizedException(__('Unable to reset the temporary download file.'));
                }
            }
            throw new LocalizedException(__('Ergonode file exceeded the redirect limit.'));
        } finally {
            fclose($stream);
        }
    }
}
