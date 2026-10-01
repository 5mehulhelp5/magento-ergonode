<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

/** Shares complete remote reads within one import with fixed credentials and languages. */
class CategoryTreeDownloadScope
{
    private int $depth = 0;
    private ?string $treeKey = null;
    /** @var array<string, mixed> */
    private array $tree = [];
    /** @var array<string, array<string, mixed>> */
    private array $presence = [];

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function execute(callable $operation): mixed
    {
        $this->depth++;
        try {
            return $operation();
        } finally {
            $this->depth--;
            if ($this->depth === 0) {
                $this->treeKey = null;
                $this->tree = [];
                $this->presence = [];
            }
        }
    }

    /**
     * @param callable(): array<string, mixed> $download
     * @return array<string, mixed>
     */
    public function tree(string $key, callable $download): array
    {
        if ($this->depth === 0) {
            return $download();
        }
        if ($this->treeKey !== $key) {
            $this->treeKey = null;
            $this->tree = [];
            $complete = $download();
            $this->tree = $complete;
            $this->treeKey = $key;
        }

        return $this->tree;
    }

    /**
     * @param callable(): array<string, mixed> $check
     * @return array<string, mixed>
     */
    public function presence(string $code, callable $check): array
    {
        if ($this->depth === 0) {
            return $check();
        }

        return $this->presence[$code] ??= $check();
    }
}
