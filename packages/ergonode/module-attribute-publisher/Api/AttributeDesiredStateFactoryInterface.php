<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;

interface AttributeDesiredStateFactoryInterface
{
    /**
     * @param string $code
     * @param array<string, string> $names
     * @return AttributeOptionStateInterface
     */
    public function createOption(string $code, array $names): AttributeOptionStateInterface;

    /**
     * @param string $code
     * @param string $type
     * @param string $scope
     * @param array<string, string> $names
     * @param array<string, bool|float|int|string> $parameters
     * @param array<string, string> $metadata
     * @param AttributeOptionStateInterface[] $options
     * @return AttributeStateInterface
     */
    public function createAttribute(
        string $code,
        string $type,
        string $scope,
        array $names,
        array $parameters,
        array $metadata,
        array $options
    ): AttributeStateInterface;
}
