<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping;

use Ergonode\Category\Api\CategoryAutoMapperInterface;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class AutoMap extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_auto_map';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly CategoryAutoMapperInterface $autoMapper,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $rawPayload = (string)$this->getRequest()->getParam('payload', '');
            $payload = $rawPayload === '' ? [] : $this->payloadDecoder->decode($rawPayload);
            $categoryTreeId = (int)($payload['category_tree_id']
                ?? $this->getRequest()->getParam('category_tree_id', 0));
            if ($categoryTreeId <= 0) {
                return $result->setData(['success' => false, 'message' => (string)__('Category Tree is required.')]);
            }

            return $result->setData(['success' => true] + $this->autoMapper->suggest(
                $categoryTreeId,
                is_array($payload['draft_mappings'] ?? null) ? $payload['draft_mappings'] : [],
                is_array($payload['draft_visibility'] ?? null) ? $payload['draft_visibility'] : []
            ));
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to preview category mappings.', ['exception' => $exception]);
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to auto-map categories.'),
            ]);
        }
    }
}
