<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use PhpParser\Node\Stmt;

final readonly class StatementSequence
{
    /** @param list<Stmt> $statements */
    public function __construct(
        public int $id,
        public string $file,
        public array $statements
    ) {
    }
}
