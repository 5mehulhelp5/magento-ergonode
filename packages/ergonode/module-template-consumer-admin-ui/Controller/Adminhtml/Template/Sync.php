<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumerAdminUi\Controller\Adminhtml\Template;

use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Sync extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_sync';
    private const string ACTION_SYNC = 'sync';
    private const string ACTION_RESET_CURSOR = 'reset-cursor';
    private const string ACTION_RESET_CURSOR_AND_SYNC = 'reset-cursor-and-sync';

    public function __construct(
        Context $context,
        private readonly TemplateSynchronizerInterface $templateSynchronizer
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
                'message' => (string)__('Unsupported template synchronization action.'),
            ]);
        }

        try {
            if ($action === self::ACTION_RESET_CURSOR) {
                $this->templateSynchronizer->resetCursor();

                return $result->setData([
                    'success' => true,
                    'message' => (string)__('The saved template cursor has been cleared.'),
                ]);
            }
            $data = $this->templateSynchronizer->execute(
                $action === self::ACTION_RESET_CURSOR_AND_SYNC
            );

            return $result->setData([
                'success' => true,
                'message' => (string)__(
                    'Templates synchronized. Imported: %1, changed: %2.',
                    $data['imported'],
                    $data['changed']
                ),
            ] + $data);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to synchronize templates: %1', $exception->getMessage()),
            ]);
        }
    }
}
