<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Model\Batch;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributePublisher\Api\OptionDefinitionPublisherInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeOptionCreator;
use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class OptionBatchPublisher
{
    public function __construct(
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly ErgonodeOptionCreator $optionCreator,
        private readonly OptionDefinitionPublisherInterface $definitionPublisher,
        private readonly SynchronizationRateLimitGuard $rateLimitGuard,
        private readonly int $maxBatchSize = 50
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $items
     * @return array<int, array{
     *     code: string,
     *     label: string,
     *     status: string,
     *     message: string,
     *     mapping?: array<string, mixed>
     * }>
     */
    public function publish(int $attributeMappingId, array $items): array
    {
        $context = $this->resolveAttributeContext($attributeMappingId);
        $attributeCode = $context['ergonode'];
        $this->validateBatch($items);
        $prepared = [];
        $results = [];

        foreach ($items as $index => $item) {
            $source = $this->normalizeSource($item);
            try {
                $this->validateSource($source);
                $state = $this->optionCreator->prepareState($context['magento'], $attributeCode, $source);
                $prepared[$index] = [
                    'source' => $source,
                    'mapping' => $this->optionCreator->prepareMapping($state),
                    'state' => $state,
                ];
            } catch (Throwable $exception) {
                if ($exception instanceof RetryAfterExceptionInterface) {
                    throw $exception;
                }
                $results[$index] = $this->failure($source, $exception);
            }
        }
        $this->rejectDuplicateGeneratedCodes($prepared, $results);

        if ($prepared !== []) {
            $synchronizationResults = $this->definitionPublisher->publishBatch(
                $attributeCode,
                array_column($prepared, 'state')
            );
            $successfulIndexes = [];
            foreach ($prepared as $index => $item) {
                $source = $item['source'];
                $code = $item['state']->getCode();
                $synchronizationResult = $synchronizationResults[$code] ?? null;
                if ($synchronizationResult === null) {
                    $results[$index] = $this->failure(
                        $source,
                        new LocalizedException(__('Missing synchronization result for option "%1".', $code))
                    );
                    continue;
                }
                $this->rateLimitGuard->throwIfLimited($synchronizationResult);
                if (!$synchronizationResult->isSuccessful()) {
                    $results[$index] = $this->failureMessage(
                        $source,
                        $synchronizationResult->getMessage() ?: (string)__('Unable to create the Ergonode option.')
                    );
                    continue;
                }
                $results[$index] = $this->success($source, $item['mapping'], $synchronizationResult);
                $successfulIndexes[] = $index;
            }

            if ($successfulIndexes !== []) {
                try {
                    $this->optionCreator->verifyPublishedOptions($attributeCode);
                } catch (Throwable $exception) {
                    if ($exception instanceof RetryAfterExceptionInterface) {
                        throw $exception;
                    }
                    foreach ($successfulIndexes as $index) {
                        $results[$index] = $this->failure($prepared[$index]['source'], $exception);
                    }
                }
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * @param array<int, array{
     *     source: array<string, mixed>,
     *     mapping: array<string, mixed>,
     *     state: \Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface
     * }> $prepared
     * @param array<int, array<string, mixed>> $results
     */
    private function rejectDuplicateGeneratedCodes(array &$prepared, array &$results): void
    {
        $indexesByCode = [];
        foreach ($prepared as $index => $item) {
            $indexesByCode[$item['state']->getCode()][] = $index;
        }

        foreach ($indexesByCode as $code => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            foreach ($indexes as $index) {
                $results[$index] = $this->failureMessage(
                    $prepared[$index]['source'],
                    (string)__('Ergonode option code "%1" is generated for more than one Magento option.', $code)
                );
                unset($prepared[$index]);
            }
        }
    }

    /** @return array{magento: string, ergonode: string} */
    private function resolveAttributeContext(int $attributeMappingId): array
    {
        $context = $attributeMappingId > 0
            ? $this->attributeMappingProvider->getMappingRow($attributeMappingId)
            : null;
        $attributeCode = is_array($context)
            ? trim((string)($context['ergonode_attribute_code'] ?? ''))
            : '';
        $magentoCode = trim((string)($context['magento_attribute_code'] ?? ''));
        if ($attributeCode === '' || $magentoCode === '') {
            throw new LocalizedException(__('Missing Ergonode attribute mapping context.'));
        }

        return ['magento' => $magentoCode, 'ergonode' => $attributeCode];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function validateBatch(array $items): void
    {
        if ($items === []) {
            throw new LocalizedException(__('Option batch cannot be empty.'));
        }
        if (count($items) > max(1, $this->maxBatchSize)) {
            throw new LocalizedException(
                __(
                    'Option batch contains %1 items; the maximum is %2.',
                    count($items),
                    max(1, $this->maxBatchSize)
                )
            );
        }
    }

    /**
     * @param  array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeSource(array $item): array
    {
        return [
            'code' => trim((string)($item['code'] ?? '')),
            'label' => trim((string)($item['label'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    private function validateSource(array $source): void
    {
        if ($source['code'] === '' || $source['label'] === '') {
            throw new LocalizedException(__('Magento option code and label are required.'));
        }
    }

    /**
     * @param  array<string, mixed> $source
     * @param  array<string, mixed> $mapping
     * @return array{code: string, label: string, status: string, message: string, mapping: array<string, mixed>}
     */
    private function success(
        array $source,
        array $mapping,
        AttributeOptionSynchronizationResultInterface $synchronizationResult
    ): array {
        $existing = $synchronizationResult->getStatus()
            === AttributeOptionSynchronizationResultInterface::STATUS_NOOP;

        return [
            'code' => (string)$source['code'],
            'label' => (string)$source['label'],
            'status' => $existing ? 'existing' : 'synchronized',
            'message' => $synchronizationResult->getMessage()
                ?: (string)__('Option has been synchronized with Ergonode.'),
            'mapping' => $mapping,
        ];
    }

    /**
     * @param  array<string, mixed> $source
     * @return array{code: string, label: string, status: string, message: string}
     */
    private function failure(array $source, Throwable $exception): array
    {
        return $this->failureMessage(
            $source,
            $exception instanceof LocalizedException
                ? $exception->getMessage()
                : (string)__('Unable to create the Ergonode option.')
        );
    }

    /**
     * @param  array<string, mixed> $source
     * @return array{code: string, label: string, status: string, message: string}
     */
    private function failureMessage(array $source, string $message): array
    {
        return [
            'code' => (string)$source['code'],
            'label' => (string)$source['label'],
            'status' => 'failed',
            'message' => $message,
        ];
    }
}
