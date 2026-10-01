<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Block\Adminhtml\CategoryTreeMapping;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use Ergonode\Category\Model\Config\Source\CategoryTreeOptions;
use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Ergonode\CategoryAdminUi\Model\CategoryTreeUiProvider;

class Index extends Template
{
    private const string ACL_TREE_SAVE = 'Ergonode_CategoryConsumer::category_tree_save';
    private const string ACL_TREE_MANAGE = 'Ergonode_CategoryConsumer::category_tree_manage';
    private const string ACL_MAPPING_REFRESH = 'Ergonode_CategoryConsumer::category_tree_mapping_refresh';
    private const string ACL_MAPPING_AUTO_MAP = 'Ergonode_CategoryConsumer::category_tree_mapping_auto_map';

    /** @var array<string, mixed>|null */
    private ?array $categoryConfig = null;

    public function __construct(
        Context $context,
        private readonly CategoryTreeMappingUiProvider $categoryTreeMappingUiProvider,
        private readonly CategoryTreeOptions $categoryTreeOptions,
        private readonly CategoryTreeUiProvider $categoryTreeUiProvider,
        private readonly Json $json,
        private readonly FormKey $categoryFormKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCategoryConfigJson(): string
    {
        return $this->json->serialize($this->getCategoryConfig());
    }

    /**
     * @return array<string, mixed>
     */
    public function getCategoryConfig(): array
    {
        if ($this->categoryConfig !== null) {
            return $this->categoryConfig;
        }

        $config = $this->categoryTreeMappingUiProvider->getConfig();
        $config['source_permissions'] = [
            'refresh' => $this->getAuthorization()->isAllowed(self::ACL_MAPPING_REFRESH),
            'disable' => $this->canEditCategoryTree(),
        ];
        $config['urls'] = ($config['urls'] ?? []) + [
            'load' => $this->getUrl('ergonode/category_tree_mapping/load'),
            'refresh' => $this->getUrl('ergonode/category_tree_mapping/refresh'),
            'validate' => $this->getUrl('ergonode/category_tree_mapping/validate'),
            'save' => $this->getUrl('ergonode/category_tree_mapping/save'),
            'delete_snapshot' => $this->getUrl('ergonode/category_tree_mapping/deleteSnapshot'),
            'settings_save' => $this->getUrl('ergonode/category_tree/save'),
            'settings_delete' => $this->getUrl('ergonode/category_tree/delete'),
            'settings_reorder' => $this->getUrl('ergonode/category_tree/reorder'),
            'tree_options_refresh' => $this->getUrl('ergonode/category_tree/refreshTreeOptions'),
        ];
        if ($this->getAuthorization()->isAllowed(self::ACL_MAPPING_AUTO_MAP)) {
            $config['urls']['auto_map'] = $this->getUrl('ergonode/category_tree_mapping/autoMap');
        }
        /** @var array<int, array<string, mixed>> $categoryTrees */
        $categoryTrees = $config['category_trees'];
        foreach ($categoryTrees as $index => $categoryTree) {
            $categoryTrees[$index]['mapping_url'] = $this->getUrl(
                'ergonode/category_tree_mapping/edit',
                ['category_tree_id' => (int)$categoryTree['category_tree_id']]
            );
        }
        $config['category_trees'] = $categoryTrees;
        $config['new_mapping_options'] = [
            'trees' => $this->categoryTreeOptions->toOptionArray(),
            'roots' => $this->categoryTreeUiProvider->getRootOptions(),
        ];
        $config['form_key'] = $this->categoryFormKey->getFormKey();
        $this->categoryConfig = $config;

        return $this->categoryConfig;
    }

    public function canEditCategoryTree(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ACL_TREE_SAVE);
    }

    public function canRefreshCategoryTrees(): bool
    {
        return $this->getAuthorization()->isAllowed(self::ACL_TREE_MANAGE);
    }
}
