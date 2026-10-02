<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Port;

interface LocalFileIndexInterface
{
    /** @return array{path:string,content_hash:string,size:int,modified_at:int}|null */
    public function get(string $path): ?array;

    /** @return list<array{path:string,content_hash:string,size:int,modified_at:int}> */
    public function find(string $contentHash): array;

    public function save(string $path, string $contentHash, int $size, int $modifiedAt): void;

    public function remove(string $path): void;

    /** @return list<array{path:string,content_hash:string,size:int,modified_at:int}> */
    public function page(string $afterPath, int $limit): array;
}
