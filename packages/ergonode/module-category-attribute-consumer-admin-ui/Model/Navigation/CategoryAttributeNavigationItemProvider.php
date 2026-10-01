<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Model\Navigation;

use Ergonode\CategoryConsumerAdminUi\Api\CategoryNavigationItemProviderInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;

class CategoryAttributeNavigationItemProvider implements CategoryNavigationItemProviderInterface
{
    public const string ATTRIBUTE_SECTION_CODE = 'category_attributes';
    public const string OPTION_SECTION_CODE = 'category_options';

    private const string ACL_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_mapping';

    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getItems(string $currentSection): array
    {
        if (!$this->authorization->isAllowed(self::ACL_RESOURCE)) {
            return [];
        }

        return [
            [
                'icon' => 'attribution-pen',
                'label' => __('Attributes'),
                'url' => $this->urlBuilder->getUrl('ergonode/category_attribute/index'),
                'is_current' => $currentSection === self::ATTRIBUTE_SECTION_CODE,
            ],
        ];
    }
}
