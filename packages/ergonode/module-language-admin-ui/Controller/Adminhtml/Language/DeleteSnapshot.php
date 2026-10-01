<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Controller\Adminhtml\Language;

use Ergonode\Language\Api\LanguageSnapshotRemoverInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class DeleteSnapshot extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Language::language_mapping_save';

    public function __construct(
        Context $context,
        private readonly LanguageSnapshotRemoverInterface $snapshotRemover,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $this->snapshotRemover->remove((string)$this->getRequest()->getParam('code', ''));

            return $result->setData([
                'success' => true,
                'message' => (string)__('The item has been removed from this list.'),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected error while deleting an Ergonode language snapshot.', [
                'exception' => $exception,
            ]);
            return $result->setData([
                'success' => false,
                'message' => (string)__(
                    'Unable to remove the item from this list. Check the Magento logs for details.'
                ),
            ]);
        }
    }
}
