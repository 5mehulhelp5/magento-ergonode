<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Data;

use Ergonode\Publisher\Api\Data\MutationVariableInterface;
use InvalidArgumentException;
use JsonException;

final readonly class MutationVariable implements MutationVariableInterface
{
    private mixed $value;

    public function __construct(
        private string $type,
        mixed $value
    ) {
        $this->value = $this->snapshotJsonValue($value);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    private function snapshotJsonValue(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            try {
                json_encode($value, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('GraphQL variable strings must contain valid UTF-8.', 0, $exception);
            }

            return $value;
        }
        if (is_float($value)) {
            if (is_finite($value)) {
                return $value;
            }

            throw new InvalidArgumentException('GraphQL variable floats must be finite.');
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'GraphQL variables must contain only JSON-compatible scalar, null and array values.'
            );
        }

        $snapshot = [];
        foreach ($value as $key => $item) {
            $snapshot[$key] = $this->snapshotJsonValue($item);
        }

        return $snapshot;
    }
}
