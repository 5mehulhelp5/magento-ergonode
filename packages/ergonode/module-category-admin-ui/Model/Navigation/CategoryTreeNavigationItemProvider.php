<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Model\Navigation;

use Ergonode\CategoryAdminUi\Api\CategoryNavigationItemProviderInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;

class CategoryTreeNavigationItemProvider implements CategoryNavigationItemProviderInterface
{
    public const string SECTION_CODE = 'categories';

    private const string ACL_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping';

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

        return [[
            'icon' => 'list-tree',
            'label' => __('Tree'),
            'url' => $this->urlBuilder->getUrl('ergonode/category_tree_mapping/edit'),
            'is_current' => $currentSection === self::SECTION_CODE,
        ]];
    }
}
