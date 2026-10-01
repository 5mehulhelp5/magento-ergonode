<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Config;

use Ergonode\Product\Api\AssignedIdentitySupportInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductIdentityModeProvider implements ProductIdentityModeProviderInterface
{
    public const string XML_PATH_SKU_MODE = 'ergonode_products/identity/sku_mode';

    /** @param AssignedIdentitySupportInterface[] $assignedIdentitySupports */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly array $assignedIdentitySupports = [],
        private readonly ?MagentoIdentityAttributeInterface $magentoIdentityAttribute = null
    ) {
    }

    public function getMode(): string
    {
        $mode = trim((string)$this->scopeConfig->getValue(self::XML_PATH_SKU_MODE));

        return $mode;
    }

    public function isAssignedModeAvailable(): bool
    {
        return $this->assignedIdentitySupports !== [];
    }

    public function assertModeSupported(string $mode): void
    {
        if ($mode === self::MODE_MAPPED) {
            return;
        }
        if ($mode !== self::MODE_ASSIGNED) {
            throw new LocalizedException(__('Unknown product identity mode "%1".', $mode));
        }
        if (!$this->isAssignedModeAvailable()) {
            throw new LocalizedException(__(
                'Independent Ergonode SKUs require product attribute mapping support. '
                . 'Restore the extension before synchronizing these products; existing identities are preserved.'
            ));
        }
    }

    public function assertModeAvailable(string $mode): void
    {
        if ($mode === self::MODE_SHARED) {
            return;
        }
        $this->assertModeSupported($mode);
        if ($mode === self::MODE_MAPPED) {
            if ($this->magentoIdentityAttribute === null) {
                throw new LocalizedException(__('Magento identity attribute support is unavailable.'));
            }
            $this->magentoIdentityAttribute->validate();
            return;
        }
        foreach ($this->assignedIdentitySupports as $support) {
            $support->validate();
        }
    }

    public function assertNewModeAvailable(string $mode): void
    {
        if ($mode === '' || $mode === self::MODE_SHARED) {
            throw new LocalizedException(__(
                'Choose assigned or mapped Ergonode SKU mode before synchronizing a new product.'
            ));
        }
        $this->assertModeAvailable($mode);
    }
}
