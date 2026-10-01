<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Controller\Adminhtml\Category\Option;

use Ergonode\CategoryAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AutoMatch extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly OptionAutoMatcherInterface $optionAutoMatcher
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            return $result->setData(
                [
                'success' => true,
                ] + $this->suggestMappings()
            );
        } catch (LocalizedException $exception) {
            return $result->setData(
                [
                'success' => false,
                'message' => $exception->getMessage(),
                ]
            );
        } catch (Throwable) {
            return $result->setData(
                [
                'success' => false,
                'message' => (string)__('Unable to prepare automatic option mappings.'),
                ]
            );
        }
    }

    /**
     * @return array{matches: array<int, array{left: array<string, mixed>, right: array<string, mixed>}>}
     */
    private function suggestMappings(): array
    {
        $payload = $this->payloadDecoder->decode((string)$this->getRequest()->getParam('payload', ''));
        $mappingId = (int)($payload['attribute_mapping_id'] ?? 0);
        if ($mappingId <= 0) {
            throw new LocalizedException(__('Missing category attribute mapping context.'));
        }

        return $this->optionAutoMatcher->suggest(
            $mappingId,
            $this->payloadDecoder->requireList(
                $payload,
                'ergonode_options',
                'Invalid Ergonode option snapshot.'
            ),
            $this->payloadDecoder->requireList(
                $payload,
                'magento_options',
                'Invalid Magento option snapshot.'
            )
        );
    }
}
