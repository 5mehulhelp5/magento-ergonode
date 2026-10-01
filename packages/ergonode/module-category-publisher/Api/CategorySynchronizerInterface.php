<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Api;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;

interface CategorySynchronizerInterface
{
    public const string MODE_CREATE_ONLY = 'create_only';
    public const string MODE_CREATE_STRICT = 'create_strict';
    public const string MODE_UPDATE = 'update';
    public const string MODE_RECONCILE = 'reconcile';

    /**
     * @param CategoryStateInterface $desiredState
     * @param string $mode
     * @return CategorySynchronizationResultInterface
     */
    public function synchronize(
        CategoryStateInterface $desiredState,
        string $mode = self::MODE_UPDATE
    ): CategorySynchronizationResultInterface;
}
