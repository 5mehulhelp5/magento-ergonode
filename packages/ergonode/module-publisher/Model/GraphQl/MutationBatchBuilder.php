<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\GraphQl;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationVariableInterface;
use Ergonode\Publisher\Api\Exception\MutationBatchCapacityException;
use Ergonode\Publisher\Api\MutationBatchBuilderInterface;
use Ergonode\Publisher\Model\Data\MutationBatch;
use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;

class MutationBatchBuilder implements MutationBatchBuilderInterface
{
    private const string IDENTIFIER_PATTERN = '/^[_A-Za-z][_0-9A-Za-z]*$/D';

    public function __construct(
        private readonly MutationAliasGenerator $aliasGenerator,
        private readonly Json $json,
        private readonly int $maxOperations = 50,
        private readonly int $maxVariablesSizeBytes = 524288
    ) {
        if ($maxOperations < 1 || $maxVariablesSizeBytes < 1) {
            throw new InvalidArgumentException('Mutation batch limits must be positive integers.');
        }
    }

    public function build(array $operations): MutationBatch
    {
        if ($operations === []) {
            return new MutationBatch('', [], []);
        }
        if (count($operations) > $this->maxOperations) {
            throw new MutationBatchCapacityException(
                sprintf('Mutation batch exceeds the %d operation limit.', $this->maxOperations)
            );
        }

        $usedAliases = [];
        $definitions = [];
        $variables = [];
        $operationsByAlias = [];
        $fields = [];
        $seenOperations = [];

        foreach ($operations as $operation) {
            if (!$operation instanceof MutationOperationInterface) {
                throw new InvalidArgumentException('Every batch item must be a mutation operation.');
            }
            $operationId = spl_object_id($operation);
            if (isset($seenOperations[$operationId])) {
                throw new InvalidArgumentException('The same mutation operation instance cannot be batched twice.');
            }
            $seenOperations[$operationId] = true;

            $field = $this->validateIdentifier($operation->getField(), 'mutation field');
            $aliasCandidate = $this->validateIdentifier($operation->getAlias() ?? $field, 'mutation alias');
            $alias = $this->aliasGenerator->generate($aliasCandidate, $usedAliases);
            $arguments = [];
            $this->validateMetadata($operation->getMetadata());

            foreach ($operation->getVariables() as $argument => $variable) {
                $argument = $this->validateIdentifier((string)$argument, 'argument');
                if (!$variable instanceof MutationVariableInterface) {
                    throw new InvalidArgumentException('Every operation variable must be a MutationVariable.');
                }
                if (!$this->isValidType($variable->getType())) {
                    throw new InvalidArgumentException('Invalid GraphQL variable type: ' . $variable->getType());
                }

                $variableName = $alias . '_' . $argument;
                $definitions[] = '$' . $variableName . ': ' . $variable->getType();
                $value = $variable->getValue();
                $this->validateJsonValue($value);
                $variables[$variableName] = $value;
                $arguments[] = $argument . ': $' . $variableName;
            }

            $selection = $this->renderSelection($operation->getResponseFields(), 2);
            $invocation = '  ' . $alias . ': ' . $field;
            if ($arguments !== []) {
                $invocation .= '(' . implode(', ', $arguments) . ')';
            }
            if ($selection !== '') {
                $invocation .= " {\n" . $selection . "\n  }";
            }

            $fields[] = $invocation;
            $operationsByAlias[$alias] = $operation;
        }

        $header = 'mutation PublishBatch';
        if ($definitions !== []) {
            $header .= "(\n  " . implode(",\n  ", $definitions) . "\n)";
        }

        $serializedVariables = $this->json->serialize($variables);
        $variablesSize = strlen($serializedVariables);
        if ($variablesSize > $this->maxVariablesSizeBytes) {
            throw new MutationBatchCapacityException(sprintf(
                'Mutation batch variables payload is %d bytes and exceeds the %d byte limit.',
                $variablesSize,
                $this->maxVariablesSizeBytes
            ));
        }

        return new MutationBatch(
            $header . " {\n" . implode("\n", $fields) . "\n}",
            $variables,
            $operationsByAlias
        );
    }

    private function validateIdentifier(string $identifier, string $kind): string
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new InvalidArgumentException('Invalid GraphQL ' . $kind . ': ' . $identifier);
        }

        return $identifier;
    }

    private function isValidType(string $type): bool
    {
        if (str_ends_with($type, '!')) {
            $type = substr($type, 0, -1);
        }

        if (preg_match(self::IDENTIFIER_PATTERN, $type)) {
            return true;
        }

        return str_starts_with($type, '[')
            && str_ends_with($type, ']')
            && $this->isValidType(substr($type, 1, -1));
    }

    private function validateJsonValue(mixed $value): void
    {
        if ($value === null || is_string($value) || is_int($value) || is_bool($value)) {
            return;
        }
        if (is_float($value) && is_finite($value)) {
            return;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('GraphQL variable values must be deeply JSON-compatible.');
        }

        foreach ($value as $item) {
            $this->validateJsonValue($item);
        }
    }

    /**
     * @param array<string, bool|float|int|string|null> $metadata
     */
    private function validateMetadata(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (!is_string($key)
                || !(is_scalar($value) || $value === null)
                || (is_float($value) && !is_finite($value))
            ) {
                throw new InvalidArgumentException(
                    'Mutation metadata must contain string keys and immutable scalar values.'
                );
            }
        }
    }

    /**
     * @param string[] $paths
     */
    private function renderSelection(array $paths, int $indent): string
    {
        if ($paths === []) {
            return '';
        }

        $tree = [];
        foreach ($paths as $path) {
            $segments = explode('.', $path);
            $branch = &$tree;
            foreach ($segments as $index => $segment) {
                $segment = $this->validateIdentifier($segment, 'response field');
                $isLeaf = $index === array_key_last($segments);
                if (isset($branch[$segment]) && $branch[$segment] === true && !$isLeaf) {
                    throw new InvalidArgumentException('A response field cannot be both a leaf and an object.');
                }
                if (!isset($branch[$segment])) {
                    $branch[$segment] = [];
                }
                if (!is_array($branch[$segment])) {
                    throw new InvalidArgumentException('A response field cannot be both a leaf and an object.');
                }
                $branch = &$branch[$segment];
            }
            if ($branch !== []) {
                throw new InvalidArgumentException('A response field cannot be both a leaf and an object.');
            }
            $branch = true;
            unset($branch);
        }

        return $this->renderSelectionTree($tree, $indent);
    }

    /**
     * @param array<string, array<string, mixed>|true> $tree
     */
    private function renderSelectionTree(array $tree, int $indent): string
    {
        $lines = [];
        $prefix = str_repeat('  ', $indent);

        foreach ($tree as $field => $children) {
            if ($children === true) {
                $lines[] = $prefix . $field;
                continue;
            }

            $lines[] = $prefix . $field . " {\n"
                . $this->renderSelectionTree($children, $indent + 1)
                . "\n" . $prefix . '}';
        }

        return implode("\n", $lines);
    }
}
