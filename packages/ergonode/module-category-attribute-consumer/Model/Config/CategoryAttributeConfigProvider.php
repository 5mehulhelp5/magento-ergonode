<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Config;

use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;

class CategoryAttributeConfigProvider
{
    public const string XML_PATH_NAME_MODE = 'ergonode_category_attributes/synchronization/name_mode';
    public const string XML_PATH_INCLUDE_IN_MENU_MODE =
        'ergonode_category_attributes/synchronization/include_in_menu_mode';
    public const string XML_PATH_INCLUDE_IN_MENU_DEFAULT =
        'ergonode_category_attributes/synchronization/include_in_menu_default';
    public const string XML_PATH_IS_ACTIVE_MODE = 'ergonode_category_attributes/synchronization/is_active_mode';
    public const string XML_PATH_IS_ACTIVE_DEFAULT =
        'ergonode_category_attributes/synchronization/is_active_default';

    public const string LEGACY_XML_PATH_SYNCHRONIZATION_ENABLED = 'ergonode_categories/attributes/status';
    public const string LEGACY_XML_PATH_NAME_MODE = 'ergonode_categories/attributes/name_mode';
    public const string LEGACY_XML_PATH_INCLUDE_IN_MENU_MODE =
        'ergonode_categories/attributes/include_in_menu_mode';
    public const string LEGACY_XML_PATH_INCLUDE_IN_MENU_DEFAULT =
        'ergonode_categories/attributes/include_in_menu_default';
    public const string LEGACY_XML_PATH_IS_ACTIVE_MODE = 'ergonode_categories/attributes/is_active_mode';
    public const string LEGACY_XML_PATH_IS_ACTIVE_DEFAULT = 'ergonode_categories/attributes/is_active_default';

    public function __construct(
        private readonly CategoryConfigProvider $categoryConfigProvider
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->categoryConfigProvider->isEnabled();
    }

    public function isAttributeSynchronizationEnabled(): bool
    {
        return $this->categoryConfigProvider->isDataSynchronizationEnabled();
    }
}
