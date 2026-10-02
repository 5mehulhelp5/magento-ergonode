<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class StatementMetric
{
    public function score(Stmt $statement): int
    {
        [$statements, $expressions] = $this->countNodes($statement);

        return (2 * $statements) + $expressions;
    }

    /** @return array{int, int} */
    private function countNodes(mixed $value): array
    {
        if (is_array($value)) {
            $statements = 0;
            $expressions = 0;
            foreach ($value as $item) {
                [$itemStatements, $itemExpressions] = $this->countNodes($item);
                $statements += $itemStatements;
                $expressions += $itemExpressions;
            }

            return [$statements, $expressions];
        }
        if (!$value instanceof Node) {
            return [0, 0];
        }

        $statements = $value instanceof Stmt ? 1 : 0;
        $expressions = $value instanceof Expr ? 1 : 0;
        foreach ($value->getSubNodeNames() as $name) {
            [$childStatements, $childExpressions] = $this->countNodes($value->{$name});
            $statements += $childStatements;
            $expressions += $childExpressions;
        }

        return [$statements, $expressions];
    }
}
