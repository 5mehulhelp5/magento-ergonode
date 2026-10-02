<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Dependency;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

final class ReferencedNameCollector extends NodeVisitorAbstract
{
    /** @var array<string, true> */
    private array $names = [];

    public function enterNode(Node $node): null
    {
        if (!$node instanceof Name) {
            return null;
        }

        $resolvedName = $node->getAttribute('resolvedName');
        $name = $resolvedName instanceof Name ? $resolvedName->toString() : $node->toString();
        if (str_contains($name, '\\')) {
            $this->names[ltrim($name, '\\')] = true;
        }

        return null;
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = array_keys($this->names);
        sort($names);

        return $names;
    }
}
