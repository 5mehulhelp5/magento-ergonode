<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Category\Batch;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Ergonode\PublisherAdminUi\Controller\Adminhtml\AbstractBatchCreate;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Create extends AbstractBatchCreate
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_save';

    public function __construct(
        Context $context,
        Json $json,
        private readonly CategoryBatchPublisher $batchPublisher
    ) {
        parent::__construct($context, $json);
    }

    protected function publishBatch(array $items): array
    {
        return $this->batchPublisher->publish(
            (int)$this->getRequest()->getParam('category_tree_id', 0),
            $items,
            $this->categoryCodes($items)
        );
    }

    protected function getBatchEntityName(): string
    {
        return 'category';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return string[]
     */
    private function categoryCodes(array $items): array
    {
        $codes = array_map(
            static fn (array $item): string => trim((string)($item['code'] ?? '')),
            $items
        );

        return array_values(array_unique(array_filter(
            $codes,
            static fn (string $code): bool => $code !== ''
        )));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{processed: int, successful: int, failed: int, skipped: int}
     */
    protected function stats(array $items): array
    {
        $failed = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['status'] ?? '') === 'failed'
        ));
        $skipped = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['status'] ?? '') === 'skipped'
        ));

        return [
            'processed' => count($items),
            'successful' => count($items) - $failed - $skipped,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }
}
