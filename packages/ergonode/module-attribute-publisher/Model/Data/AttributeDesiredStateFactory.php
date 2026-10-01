<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Data;

use Ergonode\AttributePublisher\Api\AttributeDesiredStateFactoryInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;

class AttributeDesiredStateFactory implements AttributeDesiredStateFactoryInterface
{
    public function createOption(string $code, array $names): AttributeOptionStateInterface
    {
        return new AttributeOptionState($code, $names);
    }

    public function createAttribute(
        string $code,
        string $type,
        string $scope,
        array $names,
        array $parameters,
        array $metadata,
        array $options
    ): AttributeStateInterface {
        return new AttributeState($code, $type, $scope, $names, $parameters, $metadata, $options);
    }
}
