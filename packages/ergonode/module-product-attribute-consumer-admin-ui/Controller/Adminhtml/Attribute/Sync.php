<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Controller\Adminhtml\Attribute;

use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationProcessInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Sync extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_ProductAttributeConsumer::attribute_sync';
    private const string ACTION_SYNC = 'sync';
    private const string ACTION_RESET_CURSOR = 'reset-cursor';
    private const string ACTION_RESET_CURSOR_AND_SYNC = 'reset-cursor-and-sync';

    public function __construct(
        Context $context,
        private readonly AttributeSynchronizationProcessInterface $synchronizationProcess
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $action = trim((string)$this->getRequest()->getParam(
            'synchronization_action',
            self::ACTION_SYNC
        ));

        if (!in_array($action, [
            self::ACTION_SYNC,
            self::ACTION_RESET_CURSOR,
            self::ACTION_RESET_CURSOR_AND_SYNC,
        ], true)) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unsupported attribute synchronization action.'),
            ]);
        }

        try {
            if ($action === self::ACTION_RESET_CURSOR) {
                $this->synchronizationProcess->reset();

                return $result->setData([
                    'success' => true,
                    'message' => (string)__('The attributeStream cursor has been reset.'),
                ]);
            }
            if ($action === self::ACTION_RESET_CURSOR_AND_SYNC) {
                $this->synchronizationProcess->reset();
            }
            $summary = $this->synchronizationProcess->executeUntilComplete();

            return $result->setData([
                'success' => true,
                'message' => (string)__(
                    'Attributes synchronized. Created: %1, mapped: %2, options created: %3.',
                    $summary['created_attributes'],
                    $summary['auto_mapped'],
                    $summary['options']['created']
                ),
            ] + $summary);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to synchronize attributes: %1', $exception->getMessage()),
            ]);
        }
    }
}
