<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Data;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use InvalidArgumentException;

final readonly class AttributeOptionState implements AttributeOptionStateInterface
{
    use NormalizesTranslations;

    /** @var array<string, string> */
    private array $names;

    /** @param array<string, string> $names */
    public function __construct(private string $code, array $names)
    {
        $code = trim($this->code);
        if ($code === '') {
            throw new InvalidArgumentException('Attribute option code cannot be empty.');
        }

        $this->names = $this->normalizeTranslations($names);
    }

    public function getCode(): string
    {
        return trim($this->code);
    }

    public function getNames(): array
    {
        return $this->names;
    }
}
