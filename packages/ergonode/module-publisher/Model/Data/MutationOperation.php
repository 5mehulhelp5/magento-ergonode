<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Data;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationVariableInterface;
use InvalidArgumentException;

final readonly class MutationOperation implements MutationOperationInterface
{
    /**
     * @var array<string, MutationVariableInterface>
     */
    private array $variables;

    /**
     * @var string[]
     */
    private array $responseFields;

    /**
     * @var array<string, bool|float|int|string|null>
     */
    private array $metadata;

    /**
     * @param array<string, MutationVariableInterface> $variables
     * @param string[] $responseFields
     * @param array<string, bool|float|int|string|null> $metadata
     */
    public function __construct(
        private string $field,
        array $variables,
        array $responseFields,
        array $metadata = [],
        private ?string $alias = null
    ) {
        $variableSnapshots = [];
        foreach ($variables as $argument => $variable) {
            if (!is_string($argument) || !$variable instanceof MutationVariableInterface) {
                throw new InvalidArgumentException(
                    'Mutation variables must contain string argument keys and MutationVariable values.'
                );
            }
            $variableSnapshots[$argument] = new MutationVariable($variable->getType(), $variable->getValue());
        }
        $this->variables = $variableSnapshots;

        $responseFieldSnapshots = [];
        foreach ($responseFields as $responseField) {
            if (!is_string($responseField)) {
                throw new InvalidArgumentException('Mutation response fields must be strings.');
            }
            $responseFieldSnapshots[] = $responseField;
        }
        $this->responseFields = $responseFieldSnapshots;

        $metadataSnapshots = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key) || !$this->isImmutableScalar($value)) {
                throw new InvalidArgumentException(
                    'Mutation metadata must contain string keys and immutable scalar values.'
                );
            }
            $metadataSnapshots[$key] = $value;
        }
        $this->metadata = $metadataSnapshots;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getResponseFields(): array
    {
        return $this->responseFields;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    private function isImmutableScalar(mixed $value): bool
    {
        return $value === null
            || is_string($value)
            || is_int($value)
            || is_bool($value)
            || (is_float($value) && is_finite($value));
    }
}
