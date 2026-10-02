<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ValueObject\Gallery;

final readonly class GallerySelection
{
    /** @var list<string> */
    private array $paths;

    /** @param list<string> $paths */
    public function __construct(array $paths)
    {
        $normalized = [];
        foreach ($paths as $path) {
            $path = trim($path);
            if ($path !== '') {
                $normalized[$path] = $path;
            }
        }
        $this->paths = array_values($normalized);
    }

    /** @return list<string> */
    public function paths(): array
    {
        return $this->paths;
    }
}
