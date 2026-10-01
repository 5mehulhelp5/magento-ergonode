<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface ErgonodeFileDownloaderInterface
{
    /**
     * @param string $source
     * @param string $mediaDirectory
     * @return array{relative_path: string, media_url: string, source_url: string, downloaded: bool}
     */
    public function download(string $source, string $mediaDirectory = 'ergonode/file'): array;
}
