<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;
use Ergonode\CategoryConsumer\Api\CategoryStreamAvailabilityProviderInterface;

class CategorySynchronizationActions extends Template
{
    private const string SYNC_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_sync';

    /** @var string */
    protected $_template = 'Ergonode_CategoryConsumerAdminUi::synchronization/actions.phtml';

    public function __construct(
        Context $context,
        private readonly CategoryStreamAvailabilityProviderInterface $availability,
        private readonly string $dataLabel = 'Names',
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        return $this->getAuthorization()->isAllowed(self::SYNC_RESOURCE)
            ? parent::_toHtml() : '';
    }

    /** @return array<string, Phrase> */
    public function getGroups(): array
    {
        return ['tree' => __('Tree'), 'data' => __($this->dataLabel)];
    }

    public function getBlockingReason(string $scope): string
    {
        return (string)$this->availability->getBlockingReason(data: $scope === 'data');
    }
}
