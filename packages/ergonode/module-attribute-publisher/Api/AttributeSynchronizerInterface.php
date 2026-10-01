<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;

interface AttributeSynchronizerInterface
{
    public const string MODE_CREATE_ONLY = 'create_only';
    public const string MODE_UPDATE = 'update';
    public const string MODE_RECONCILE = 'reconcile';

    /**
     * @param AttributeStateInterface $desiredState
     * @param string $mode
     * @param AttributeStateInterface|null $initialState Fresh state already read by the caller; used for the
     *     first stage only.
     * @return AttributeSynchronizationResultInterface
     */
    public function synchronize(
        AttributeStateInterface $desiredState,
        string $mode = self::MODE_UPDATE,
        ?AttributeStateInterface $initialState = null
    ): AttributeSynchronizationResultInterface;
}
