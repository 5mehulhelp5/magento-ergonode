<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Ui\DataProvider\Product\Form\Modifier;

use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Fieldset;

class ErgonodeIdentity extends AbstractModifier
{
    private const string GROUP = 'ergonode_identity';
    private const string FIELD = 'ergonode_sku';
    private const string PLANNED_FIELD = 'planned_ergonode_sku';

    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        private readonly MagentoIdentityAttributeInterface $identityAttribute
    ) {
    }

    public function modifyMeta(array $meta): array
    {
        $meta[self::GROUP] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Fieldset::NAME,
                        'label' => __('Ergonode Identity'),
                        'collapsible' => true,
                        'sortOrder' => 15,
                    ],
                ],
            ],
            'children' => [
                self::FIELD => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => Field::NAME,
                                'formElement' => 'input',
                                'dataType' => 'text',
                                'dataScope' => self::FIELD,
                                'label' => __('Ergonode SKU'),
                                'disabled' => true,
                                'notice' => __(
                                    'Read-only native Ergonode identity. '
                                    . 'The product mapping table is the source of truth.'
                                ),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($this->showsMappedPreview()) {
            $meta[self::GROUP]['children'][self::PLANNED_FIELD] = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'componentType' => Field::NAME,
                            'formElement' => 'input',
                            'dataType' => 'text',
                            'dataScope' => self::PLANNED_FIELD,
                            'label' => __('Ergonode SKU for first publication'),
                            'disabled' => true,
                            'notice' => __('Current saved value of the configured Magento identity attribute. '
                                . 'Publication requires a non-empty, unique value of at most 64 bytes.'),
                        ],
                    ],
                ],
            ];
        }

        return $meta;
    }

    public function modifyData(array $data): array
    {
        $productId = (int)$this->locator->getProduct()->getId();
        if ($productId < 1 || !isset($data[$productId][self::DATA_SOURCE_DEFAULT])) {
            return $data;
        }
        $identity = $this->identityService->getIdentitiesByProductIds([$productId])[$productId] ?? null;
        $data[$productId][self::DATA_SOURCE_DEFAULT][self::FIELD] = $identity?->getErgonodeSku();
        if ($identity === null
            && $this->identityModeProvider->getMode() === ProductIdentityModeProviderInterface::MODE_MAPPED
        ) {
            try {
                $data[$productId][self::DATA_SOURCE_DEFAULT][self::PLANNED_FIELD] =
                    $this->identityAttribute->getValuesByProductIds([$productId])[$productId] ?? '';
            } catch (LocalizedException) {
                $data[$productId][self::DATA_SOURCE_DEFAULT][self::PLANNED_FIELD] = '';
            }
        }

        return $data;
    }

    private function showsMappedPreview(): bool
    {
        $productId = (int)$this->locator->getProduct()->getId();
        return $productId > 0
            && $this->identityModeProvider->getMode() === ProductIdentityModeProviderInterface::MODE_MAPPED
            && !isset($this->identityService->getIdentitiesByProductIds([$productId])[$productId]);
    }
}
