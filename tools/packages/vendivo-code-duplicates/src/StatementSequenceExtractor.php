<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\HaltCompiler;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;

final class StatementSequenceExtractor
{
    private int $nextId = 1;

    /** @var list<StatementSequence> */
    private array $sequences = [];

    private string $file = '';

    /**
     * @param list<Stmt> $statements
     * @return list<StatementSequence>
     */
    public function extract(string $file, array $statements): array
    {
        $this->sequences = [];
        $this->file = $file;
        $this->collect($statements);

        return $this->sequences;
    }

    private function collect(mixed $value): void
    {
        if (is_array($value)) {
            if ($this->isStatementList($value)) {
                /** @var list<Stmt> $statements */
                $statements = array_values($value);
                $this->addExecutableRuns($statements);
            }
            foreach ($value as $item) {
                $this->collect($item);
            }

            return;
        }
        if (!$value instanceof Node) {
            return;
        }

        foreach ($value->getSubNodeNames() as $name) {
            $this->collect($value->{$name});
        }
    }

    /** @param array<array-key, mixed> $value */
    private function isStatementList(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        foreach ($value as $item) {
            if (!$item instanceof Stmt) {
                return false;
            }
        }

        return true;
    }

    /** @param list<Stmt> $statements */
    private function addExecutableRuns(array $statements): void
    {
        $run = [];
        foreach ($statements as $statement) {
            if ($this->isExecutable($statement)) {
                $run[] = $statement;
                continue;
            }
            $this->addRun($run);
            $run = [];
        }
        $this->addRun($run);
    }

    /** @param list<Stmt> $run */
    private function addRun(array $run): void
    {
        if ($run === []) {
            return;
        }

        $this->sequences[] = new StatementSequence($this->nextId++, $this->file, $run);
    }

    private function isExecutable(Stmt $statement): bool
    {
        return !$statement instanceof ClassLike
            && !$statement instanceof FunctionLike
            && !$statement instanceof Property
            && !$statement instanceof ClassConst
            && !$statement instanceof TraitUse
            && !$statement instanceof EnumCase
            && !$statement instanceof Const_
            && !$statement instanceof Namespace_
            && !$statement instanceof Use_
            && !$statement instanceof GroupUse
            && !$statement instanceof Declare_
            && !$statement instanceof InlineHTML
            && !$statement instanceof HaltCompiler
            && !$statement instanceof Nop;
    }
}
