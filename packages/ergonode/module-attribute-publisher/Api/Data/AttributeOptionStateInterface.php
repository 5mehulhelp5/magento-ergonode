<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api\Data;

interface AttributeOptionStateInterface
{
    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @return array<string, string>
     */
    public function getNames(): array;
}
