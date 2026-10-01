<?php

declare(strict_types=1);

namespace Ergonode\Product\Api;

interface ProductIdentityModeProviderInterface
{
    public const string MODE_SHARED = 'shared';
    public const string MODE_ASSIGNED = 'assigned';
    public const string MODE_MAPPED = 'mapped';

    /**
     * Return the configured identity mode for new product bindings.
     *
     * @return string
     */
    public function getMode(): string;

    /**
     * Whether an attribute extension provides assigned identity support.
     *
     * @return bool
     */
    public function isAssignedModeAvailable(): bool;

    /**
     * Validate a configuration choice before its attribute mapping is configured.
     *
     * @param string $mode
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assertModeSupported(string $mode): void;

    /**
     * Validate a configured or already persisted identity mode without changing it.
     *
     * @param string $mode
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assertModeAvailable(string $mode): void;

    /**
     * Validate a mode before creating a new binding. Historical shared bindings are excluded.
     *
     * @param string $mode
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assertNewModeAvailable(string $mode): void;
}
