<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Model\Batch;

use Ergonode\ProductAttributePublisher\Api\AttributeDefinitionPublisherInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeAttributeCreator;
use Ergonode\Core\Api\Exception\RetryAfterExceptionInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AttributeBatchPublisher
{
    public function __construct(
        private readonly ErgonodeAttributeCreator $attributeCreator,
        private readonly AttributeDefinitionPublisherInterface $definitionPublisher,
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
    public function publish(array $items): array
    {
        $this->validateBatch($items);
        $normalized = [];
        /**
 * @var array<string, array{mapping: array<string, mixed>, state: AttributeStateInterface}> $prepared
*/
        $prepared = [];
        $states = [];
        $stateOwners = [];
        $results = [];

        foreach ($items as $index => $item) {
            $key = '__item_' . $index;
            $normalized[$key] = $this->normalizeSource($item);
        }

        $duplicateCodes = $this->duplicateCodes($normalized);
        foreach ($normalized as $key => $source) {
            if (isset($duplicateCodes[$source['code']])) {
                $results[$key] = $this->failureMessage(
                    $source,
                    (string)__('Magento attribute code "%1" occurs more than once in the batch.', $source['code'])
                );
                continue;
            }
            try {
                $this->validateSource($source);
                $mapping = $this->attributeCreator->prepareMapping($source);
                $state = $this->attributeCreator->prepareState($source);
                $prepared[$key] = ['mapping' => $mapping, 'state' => $state];
                $states[] = $state;
                $stateOwners[$state->getCode()] = $key;
            } catch (Throwable $exception) {
                if ($exception instanceof RetryAfterExceptionInterface) {
                    throw $exception;
                }
                $results[$key] = $this->failure($source, $exception);
            }
        }

        if ($states !== []) {
            foreach ($this->definitionPublisher->publishBatch($states) as $code => $syncResult) {
                $key = $stateOwners[$code] ?? '';
                if (!isset($normalized[$key], $prepared[$key])) {
                    throw new LocalizedException(__('Attribute batch result has no source item.'));
                }
                $this->rateLimitGuard->throwIfLimited($syncResult);
                if (!$syncResult->isSuccessful()) {
                    $results[$key] = $this->failureMessage(
                        $normalized[$key],
                        $syncResult->getMessage() ?: (string)__('Unable to create the Ergonode attribute.')
                    );
                    continue;
                }
                try {
                    $this->attributeCreator->verifyPublishedState($prepared[$key]['state']);
                    $mapping = $prepared[$key]['mapping'];
                    $mapping['pending_create'] = false;
                    $results[$key] = $this->success(
                        $normalized[$key],
                        $mapping,
                        $syncResult->getStatus() === AttributeSynchronizationResultInterface::STATUS_NOOP
                            ? 'existing'
                            : 'synchronized',
                        $syncResult->getMessage()
                    );
                } catch (Throwable $exception) {
                    if ($exception instanceof RetryAfterExceptionInterface) {
                        throw $exception;
                    }
                    $results[$key] = $this->failure($normalized[$key], $exception);
                }
            }
        }

        return array_map(
            static fn (string $key): array => $results[$key],
            array_keys($normalized)
        );
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @return array<string, true>
     */
    private function duplicateCodes(array $sources): array
    {
        $counts = [];
        foreach ($sources as $source) {
            $code = (string)$source['code'];
            if ($code !== '') {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }

        return array_fill_keys(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)), true);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function validateBatch(array $items): void
    {
        if ($items === []) {
            throw new LocalizedException(__('Attribute batch cannot be empty.'));
        }
        if (count($items) > max(1, $this->maxBatchSize)) {
            throw new LocalizedException(
                __(
                    'Attribute batch contains %1 items; the maximum is %2.',
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
            'label' => trim((string)($item['label'] ?? $item['code'] ?? '')),
            'type' => trim((string)($item['type'] ?? '')),
            'scope' => trim((string)($item['scope'] ?? '')),
            'target_type' => trim((string)($item['target_type'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    private function validateSource(array $source): void
    {
        if ($source['code'] === '' || $source['label'] === '' || $source['target_type'] === '') {
            throw new LocalizedException(__('Magento attribute code, label and Ergonode type are required.'));
        }
    }

    /**
     * @param  array<string, mixed> $source
     * @param  array<string, mixed> $mapping
     * @return array{code: string, label: string, status: string, message: string, mapping: array<string, mixed>}
     */
    private function success(array $source, array $mapping, string $status, ?string $message): array
    {
        return [
            'code' => (string)$source['code'],
            'label' => (string)$source['label'],
            'status' => $status,
            'message' => $message ?: (string)__('Attribute has been synchronized with Ergonode.'),
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
                : (string)__('Unable to create the Ergonode attribute.')
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
