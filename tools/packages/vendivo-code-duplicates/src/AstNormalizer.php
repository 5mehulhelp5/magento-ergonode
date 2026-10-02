<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use JsonException;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;

final class AstNormalizer
{
    /** @var array<string, string> */
    private array $variables = [];

    private int $nextVariable = 1;

    public function __construct(private readonly bool $ignoreLiterals = false)
    {
    }

    /**
     * @param list<Stmt> $statements
     * @throws JsonException
     */
    public function normalize(array $statements, bool $coarse = false): string
    {
        $this->variables = [];
        $this->nextVariable = 1;

        return json_encode(
            array_map(fn (Stmt $statement): mixed => $this->normalizeValue($statement, $coarse), $statements),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    }

    private function normalizeValue(mixed $value, bool $coarse): mixed
    {
        if ($value instanceof Variable && is_string($value->name)) {
            return [
                'node' => $value->getType(),
                'name' => $this->normalizeVariable($value->name, $coarse),
            ];
        }
        if ($value instanceof Name) {
            return ['node' => $value->getType(), 'name' => base64_encode($value->toString())];
        }
        if ($value instanceof String_) {
            return [
                'node' => $value->getType(),
                'value' => $this->ignoreLiterals ? 'string' : base64_encode($value->value),
            ];
        }
        if ($this->ignoreLiterals && $value instanceof LNumber) {
            return ['node' => $value->getType(), 'value' => 'integer'];
        }
        if ($this->ignoreLiterals && $value instanceof DNumber) {
            return ['node' => $value->getType(), 'value' => 'float'];
        }
        if ($value instanceof Node) {
            $normalized = ['node' => $value->getType()];
            foreach ($value->getSubNodeNames() as $name) {
                $normalized[$name] = $this->normalizeValue($value->{$name}, $coarse);
            }

            return $normalized;
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeValue($item, $coarse), $value);
        }
        if (is_string($value)) {
            return ['string' => base64_encode($value)];
        }

        return $value;
    }

    private function normalizeVariable(string $name, bool $coarse): string
    {
        if ($name === 'this') {
            return 'this';
        }
        if ($coarse) {
            return 'variable';
        }

        return $this->variables[$name] ??= 'variable_' . $this->nextVariable++;
    }
}
