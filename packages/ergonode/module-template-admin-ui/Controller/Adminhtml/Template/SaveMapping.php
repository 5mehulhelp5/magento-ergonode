<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Controller\Adminhtml\Template;

use Ergonode\TemplateAdminUi\Model\Mapping\WorkspaceMappingSaver;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Throwable;

class SaveMapping extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_save';

    public function __construct(
        Context $context,
        private readonly JsonSerializer $jsonSerializer,
        private readonly WorkspaceMappingSaver $workspaceMappingSaver
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            return $result->setData([
                'success' => true,
                'message' => (string)__('Template mappings have been saved.'),
                'stats' => $this->saveMappings(),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to save template mappings: %1', $exception->getMessage()),
            ]);
        }
    }

    /** @return array<string, int> */
    private function saveMappings(): array
    {
        $mappings = $this->jsonSerializer->unserialize(
            (string)$this->getRequest()->getParam('mappings', '{}')
        );
        if (!is_array($mappings)) {
            throw new LocalizedException(__('Invalid template mapping payload.'));
        }

        $visibility = $this->jsonSerializer->unserialize(
            (string)$this->getRequest()->getParam('visibility', '[]')
        );
        if (!is_array($visibility)) {
            throw new LocalizedException(__('Invalid template visibility payload.'));
        }

        return $this->workspaceMappingSaver->save($mappings, $visibility);
    }
}
