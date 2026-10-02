<?php

declare(strict_types=1);

namespace Vendivo\Sniffs\Plugins;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/** Detect only simple, unambiguous around wrappers; complex cases still need review. */
class PreferBeforeAfterSniff implements Sniff
{
    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    public function process(File $file, $stackPtr): void
    {
        if ($stackPtr !== $file->findNext(T_OPEN_TAG, 0)) { return; }
        // PHPCS loads its own autoloader; the project owns the AST parser dependency.
        require_once dirname(__DIR__, 6) . '/vendor/autoload.php';
        try {
            $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($file->getTokensAsString(0, $file->numTokens));
        } catch (\PhpParser\Error) {
            return; // Syntax errors belong to the syntax/static analyzer.
        }
        $finder = new NodeFinder();
        foreach ($finder->findInstanceOf($nodes ?? [], Node\Stmt\ClassMethod::class) as $method) {
            if (!str_starts_with($method->name->toString(), 'around') || $method->stmts === null) { continue; }
            $statements = $method->stmts;
            $proceed = $finder->find($statements, static fn (Node $node): bool => $node instanceof Node\Expr\Variable && $node->name === 'proceed');
            if (count($proceed) !== 1 || $statements === []) { continue; }
            $last = $statements[count($statements) - 1];
            $replacement = null;
            if ($last instanceof Node\Stmt\Return_ && $this->isProceed($last->expr)
                && $this->onlyExpressions(array_slice($statements, 0, -1))) {
                $replacement = count($statements) === 1 ? 'remove the redundant plugin' : 'use a before plugin';
            }
            $first = $statements[0];
            if ($first instanceof Node\Stmt\Expression && $first->expr instanceof Node\Expr\Assign
                && $this->isProceed($first->expr->expr) && $this->forwardsArguments($method, $first->expr->expr)
                && $last instanceof Node\Stmt\Return_
                && $first->expr->var instanceof Node\Expr\Variable && $last->expr instanceof Node\Expr\Variable
                && $first->expr->var->name === $last->expr->name
                && $this->onlyExpressions(array_slice($statements, 1, -1))) {
                $replacement = 'use an after plugin';
            }
            if ($replacement !== null) {
                $file->addErrorOnLine('Simple around wrapper: %s. Reserve around for control that must enclose the original call.',
                    $method->getStartLine(), 'UnnecessaryAround', [$replacement]);
            }
        }
    }

    private function forwardsArguments(Node\Stmt\ClassMethod $method, Node\Expr\FuncCall $call): bool
    {
        $parameters = array_slice($method->params, 2);
        if (count($parameters) !== count($call->args)) { return false; }
        foreach ($parameters as $index => $parameter) {
            $argument = $call->args[$index];
            if (!$argument instanceof Node\Arg || !$argument->value instanceof Node\Expr\Variable
                || $argument->value->name !== $parameter->var->name
                || $argument->unpack !== $parameter->variadic || $parameter->byRef || $argument->name !== null) {
                return false;
            }
        }
        return true;
    }

    private function isProceed(?Node $node): bool
    {
        return $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Expr\Variable && $node->name->name === 'proceed';
    }

    /** @param list<Node\Stmt> $statements */
    private function onlyExpressions(array $statements): bool
    {
        foreach ($statements as $statement) {
            if (!$statement instanceof Node\Stmt\Expression) { return false; }
        }
        return true;
    }
}
