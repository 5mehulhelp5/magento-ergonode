<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Block\Adminhtml\CategoryTreeHistory;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class MappingPanel extends Template
{
    private const string HISTORY_RESOURCE = 'Ergonode_CategoryConsumerHistory::view';

    public function __construct(
        Context $context,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canViewHistory(): bool
    {
        return $this->getAuthorization()->isAllowed(self::HISTORY_RESOURCE);
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'urls' => [
                'operations' => $this->getUrl('ergonode/category_tree_history/operations'),
                'history' => $this->getUrl('ergonode/category_tree_history/index'),
            ],
        ]);
    }
}
