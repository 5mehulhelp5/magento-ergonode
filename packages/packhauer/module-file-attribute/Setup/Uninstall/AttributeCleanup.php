<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Setup\Uninstall;

final readonly class AttributeCleanup
{
    /**
     * @param list<string> $attributeCodes
     * @param list<string> $preservedPaths
     */
    public function __construct(
        public array $attributeCodes,
        public array $preservedPaths
    ) {
    }
}
