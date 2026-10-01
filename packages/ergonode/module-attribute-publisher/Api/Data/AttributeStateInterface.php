<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api\Data;

interface AttributeStateInterface
{
    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getScope(): string;

    /**
     * @return array<string, string>
     */
    public function getNames(): array;

    /**
     * @return array<string, bool|float|int|string>
     */
    public function getParameters(): array;

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array;

    /**
     * @return AttributeOptionStateInterface[]
     */
    public function getOptions(): array;
}
