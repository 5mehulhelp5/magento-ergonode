<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Option;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionAutoMatchProcess;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AutoMatch extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_save';

    public function __construct(
        Context $context,
        private readonly MappingPayloadDecoder $payloadDecoder,
        private readonly OptionAutoMatchProcess $optionAutoMatchProcess
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
     * @return array{matches: array<int, array{left: array<string, mixed>, right: array<string, mixed>}>,
     *     conflicts: array<string, string>, unmatched: array<int, string>}
     */
    private function suggestMappings(): array
    {
        $payload = $this->payloadDecoder->decode((string)$this->getRequest()->getParam('payload', ''));
        $mappingId = (int)($payload['attribute_mapping_id'] ?? 0);
        if ($mappingId <= 0) {
            throw new LocalizedException(__('Missing attribute mapping context.'));
        }

        return $this->optionAutoMatchProcess->suggest(
            $mappingId,
            $this->availableCodes($payload['ergonode_options'] ?? null),
            $this->availableCodes($payload['magento_options'] ?? null)
        );
    }

    /** @return list<string> */
    private function availableCodes(mixed $options): array
    {
        $codes = [];
        foreach (is_array($options) ? $options : [] as $option) {
            $code = is_array($option) ? trim((string)($option['code'] ?? '')) : '';
            if ($code !== '') {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }
}
