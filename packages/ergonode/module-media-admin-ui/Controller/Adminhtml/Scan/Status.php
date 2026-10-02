<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Controller\Adminhtml\Scan;

use Ergonode\Media\Api\ScanStatusProviderInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Status extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Media::scan';

    public function __construct(Context $context, private readonly ScanStatusProviderInterface $status)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
            'success' => true, 'scan' => $this->status->getStatus(),
        ]);
    }
}
