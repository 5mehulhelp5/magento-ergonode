<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Controller\Adminhtml\Scan;

use Ergonode\Media\Api\ScanRequesterInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Start extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Media::scan';

    public function __construct(Context $context, private readonly ScanRequesterInterface $scanner)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $this->scanner->request((string)$this->getRequest()->getParam('verify', '0') === '1');
            return $result->setData(['success' => true]);
        } catch (LocalizedException $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false, 'message' => $exception->getMessage(),
            ]);
        }
    }
}
