<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Data;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use InvalidArgumentException;

final readonly class AttributeState implements AttributeStateInterface
{
    use NormalizesTranslations;

    /** @var array<string, string> */
    private array $names;

    /** @var array<string, bool|float|int|string> */
    private array $parameters;

    /** @var array<string, string> */
    private array $metadata;

    /** @var AttributeOptionStateInterface[] */
    private array $options;

    /**
     * @param array<string, string> $names
     * @param array<string, bool|float|int|string> $parameters
     * @param array<string, string> $metadata
     * @param AttributeOptionStateInterface[] $options
     */
    public function __construct(
        private string $code,
        private string $type,
        private string $scope,
        array $names = [],
        array $parameters = [],
        array $metadata = [],
        array $options = []
    ) {
        if (trim($this->code) === '' || trim($this->type) === '' || trim($this->scope) === '') {
            throw new InvalidArgumentException('Attribute code, type and scope are required.');
        }
        $this->names = $this->normalizeTranslations($names);
        ksort($parameters);
        $this->parameters = $parameters;
        ksort($metadata);
        $this->metadata = $metadata;
        $seen = [];
        foreach ($options as $option) {
            if (!$option instanceof AttributeOptionStateInterface || isset($seen[$option->getCode()])) {
                throw new InvalidArgumentException('Attribute options must have unique stable codes.');
            }
            $seen[$option->getCode()] = true;
        }
        $this->options = array_values($options);
    }

    public function getCode(): string
    {
        return trim($this->code);
    }

    public function getType(): string
    {
        return strtolower(trim($this->type));
    }

    public function getScope(): string
    {
        return strtoupper(trim($this->scope));
    }

    public function getNames(): array
    {
        return $this->names;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
