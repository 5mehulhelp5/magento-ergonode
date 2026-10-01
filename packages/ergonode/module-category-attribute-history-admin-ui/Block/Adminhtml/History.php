<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistoryAdminUi\Block\Adminhtml;

use Ergonode\CategoryAttributeHistory\Api\HistoryQueryInterface;
use Ergonode\CategoryConsumerAdminUi\Model\Navigation\CategoryNavigationGroupProvider;
use Ergonode\CategoryAttributeHistoryAdminUi\Model\HistoryView;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\JsonHexTag;

class History extends Template
{
    public function __construct(
        Context $context,
        private readonly HistoryQueryInterface $historyQuery,
        private readonly JsonHexTag $json,
        private readonly CategoryNavigationGroupProvider $navigation,
        private readonly HistoryView $historyView,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        $page = $this->historyQuery->getOperations();
        $operationId = (int)$this->getRequest()->getParam('operation_id');
        if ($operationId <= 0) {
            $operationId = (int)($page['items'][0]['operation_id'] ?? 0);
        }

        return $this->json->serialize([
            'page' => $page,
            'state' => $this->historyView->summary(
                $operationId > 0 ? $this->historyQuery->getState($operationId) : null
            ),
            'requested_operation_id' => $operationId,
            'urls' => [
                'state' => $this->getUrl('ergonode/category_attribute_history/state'),
                'operations' => $this->getUrl('ergonode/category_attribute_history/operations'),
                'mapping' => $this->getMappingUrl(),
            ],
            'icons' => [
                'source' => $this->getViewFileUrl('Ergonode_CoreAdminUi::images/m2_configuration.svg'),
                'target' => $this->getViewFileUrl('Ergonode_CoreAdminUi::images/magento-mark.svg'),
            ],
        ]);
    }

    private function getMappingUrl(): string
    {
        $group = $this->navigation->getGroup('category_attributes');
        foreach ($group['items'] ?? [] as $item) {
            if ($item['is_current']) {
                return $item['url'];
            }
        }

        return '';
    }
}
