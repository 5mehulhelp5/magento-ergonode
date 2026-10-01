<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface ErgonodeAttributeProviderInterface
{
    /**
     * @return array<string, array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     parameters: array<string, bool|string>,
     *     active: bool
     * }>
     */
    public function getAttributeMap(): array;
}
