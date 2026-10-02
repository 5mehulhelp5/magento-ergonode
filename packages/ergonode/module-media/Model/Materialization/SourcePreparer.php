<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Materialization;

use Ergonode\Core\Api\DownloadSourcePolicyInterface;
use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Download\DownloadGuard;
use Ergonode\Media\Model\GraphQl\MultimediaClient;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Lock\LockManagerInterface;

class SourcePreparer
{
    public function __construct(
        private readonly MediaRepository $repository,
        private readonly MultimediaClient $client,
        private readonly CurlFactory $curlFactory,
        private readonly DownloadSourcePolicyInterface $downloadPolicy,
        private readonly DownloadGuard $guard,
        private readonly DirectoryList $directories,
        private readonly File $file,
        private readonly LockManagerInterface $locks
    ) {
    }

    public function prepare(Asset $asset): Asset
    {
        $lock = 'ergonode_media_source_' . hash('sha256', $asset->sourcePath);
        if (!$this->locks->lock($lock, 60)) {
            throw new LocalizedException(__('Ergonode media source is being downloaded.'));
        }
        try {
            $asset = $this->repository->getAsset($asset->id);
            if ($asset->status === 'active' && $asset->contentHash !== null && $asset->cachePath !== null
                && $this->file->fileExists($this->varPath($asset->cachePath))
            ) {
                return $asset;
            }
            if ($asset->url === null || $asset->url === '') {
                $media = $this->guard->run(
                    fn (): array => $this->client->get($asset->sourcePath)
                );
                $this->repository->updateMetadata($asset->id, $media, $asset->revision);
                $asset = $this->repository->getAsset($asset->id);
            }
            $body = $this->guard->run(fn (): string => $this->download($asset));
            $hash = hash('sha256', $body, true);
            $sourceHash = hash('sha256', $asset->sourcePath);
            $cache = 'ergonode/media/' . substr($sourceHash, 0, 2) . '/' . $sourceHash
                . '-' . substr(bin2hex($hash), 0, 16) . '.' . $asset->extension;
            $absolute = $this->varPath($cache);
            if (!$this->file->fileExists($absolute)) {
                $this->file->checkAndCreateFolder($this->file->dirname($absolute));
                $this->file->write($absolute, $body);
            }
            $this->repository->activate($asset->id, $hash, $cache, strlen($body), $asset->revision);
            $prepared = $this->repository->getAsset($asset->id);
            if ($prepared->revision !== $asset->revision || $prepared->status !== 'active') {
                throw new LocalizedException(__('Ergonode media changed while its file was being downloaded.'));
            }
            return $prepared;
        } finally {
            $this->locks->unlock($lock);
        }
    }

    private function download(Asset $asset): string
    {
        $this->downloadPolicy->authorize((string)$asset->url);
        $curl = $this->curlFactory->create();
        $curl->setHeaders(['Accept' => '*/*']);
        $curl->setTimeout(60);
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        $curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $curl->setOption(CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        $curl->get((string)$asset->url);
        $body = (string)$curl->getBody();
        if ($curl->getStatus() < 200 || $curl->getStatus() >= 300 || $body === '') {
            throw new LocalizedException(__('Unable to download Ergonode media.'));
        }
        return $body;
    }

    private function varPath(string $path): string
    {
        return rtrim($this->directories->getPath(DirectoryList::VAR_DIR), '/') . '/' . ltrim($path, '/');
    }
}
