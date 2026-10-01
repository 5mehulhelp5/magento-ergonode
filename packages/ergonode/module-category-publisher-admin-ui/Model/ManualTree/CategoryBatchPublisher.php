<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\CategoryPublisher\Api\CategoryBatchSynchronizerInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryBatchPublisher
{
    public const int MAX_BATCH_SIZE = 50;

    public function __construct(
        private readonly CategoryCreationStateBuilder $stateBuilder,
        private readonly CategoryBatchSynchronizerInterface $batchSynchronizer,
        private readonly CategoryRemoteIdentityResolver $identityResolver,
        private readonly CategoryCreationCollisionLogger $collisionLogger,
        private readonly CategoryPublicationCheckpoint $checkpoint,
        private readonly CategoryPublicationDetails $publicationDetails
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param string[] $allowExistingCodes Codes explicitly allowed to reuse when they already exist remotely.
     * @return array<int, array{code: string, label: string, status: string, message: string, remote_id?: string}>
     */
    public function publish(int $categoryTreeId, array $items, array $allowExistingCodes = []): array
    {
        $this->validateBatch($categoryTreeId, $items);
        $createdCodes = $this->checkpoint->get($categoryTreeId, $items);
        $allowExisting = array_fill_keys(array_filter(
            array_map('trim', $allowExistingCodes),
            static fn (string $code): bool => $code !== ''
        ), true);
        $normalized = [];
        $states = [];
        $stateOwners = [];
        $codeOwners = [];
        $results = [];

        foreach ($items as $index => $item) {
            $code = trim((string)($item['code'] ?? ''));
            $key = '__item_' . $index;
            $normalized[$key] = $this->normalizeItem($item, $code, isset($allowExisting[$code]));
            $validationError = $this->stateBuilder->validationError(
                $normalized[$key]['code'],
                $normalized[$key]['label']
            );
            if ($validationError !== null) {
                $results[$key] = $this->resultItem($normalized[$key], 'failed', $validationError);
                continue;
            }
            if (isset($codeOwners[$code])) {
                $winner = $normalized[$codeOwners[$code]];
                $message = $this->stateBuilder->collisionMessage(
                    $normalized[$key]['code'],
                    $normalized[$key]['label']
                );
                $this->collisionLogger->warning(
                    $categoryTreeId,
                    $code,
                    $normalized[$key]['magento_category_id'],
                    $normalized[$key]['label'],
                    'batch',
                    $winner['magento_category_id'],
                    $winner['label']
                );
                $results[$key] = $this->resultItem($normalized[$key], 'skipped', $message);
                continue;
            }
            $codeOwners[$code] = $key;
            if ($normalized[$key]['pending_create'] && isset($createdCodes[$code])) {
                $results[$key] = $this->successfulItem($normalized[$key], 'synchronized');
            } elseif ($normalized[$key]['pending_create']) {
                $states[] = $this->stateBuilder->build($code, $normalized[$key]['label']);
                $stateOwners[$code] = $key;
            }
        }

        if ($states !== []) {
            $syncResults = $this->batchSynchronizer->synchronizeBatch(
                $states,
                CategorySynchronizerInterface::MODE_CREATE_STRICT
            );
            foreach ($syncResults as $code => $syncResult) {
                if ($syncResult->getStatus() === CategorySynchronizationResultInterface::STATUS_SUCCESS
                    && $syncResult->getReferenceStatus() === CategorySynchronizationResultInterface::REFERENCE_PRESENT
                ) {
                    $createdCodes[$code] = true;
                }
            }
            $this->checkpoint->save($categoryTreeId, $items, array_keys($createdCodes));
            $this->publicationDetails->remember($categoryTreeId, $syncResults);
            $this->throwForRateLimit($syncResults);
            foreach ($syncResults as $code => $syncResult) {
                $key = $stateOwners[$code];
                if ($syncResult->getStatus() === CategorySynchronizationResultInterface::STATUS_NOOP
                    && !$normalized[$key]['allow_existing']
                ) {
                    $message = $this->stateBuilder->collisionMessage(
                        $normalized[$key]['code'],
                        $normalized[$key]['label']
                    );
                    $this->collisionLogger->warning(
                        $categoryTreeId,
                        (string)$code,
                        $normalized[$key]['magento_category_id'],
                        $normalized[$key]['label'],
                        'ergonode'
                    );
                    $results[$key] = $this->resultItem($normalized[$key], 'skipped', $message);
                    continue;
                }
                if (!$syncResult->isSuccessful()) {
                    $results[$key] = $this->resultItem(
                        $normalized[$key],
                        'failed',
                        $syncResult->getMessage() ?: (string)__('Unable to create the Ergonode category.')
                    );
                    continue;
                }
                $results[$key] = $this->successfulItem(
                    $normalized[$key],
                    $syncResult->getStatus() === CategorySynchronizationResultInterface::STATUS_NOOP
                        ? 'existing'
                        : 'synchronized'
                );
            }
        }

        $eligibleItems = [];
        foreach ($normalized as $key => $item) {
            if (!isset($results[$key]) || !in_array($results[$key]['status'], ['failed', 'skipped'], true)) {
                $eligibleItems[] = $item;
            }
        }
        $remoteIds = $eligibleItems !== []
            ? $this->identityResolver->resolve($categoryTreeId, $eligibleItems)
            : [];
        foreach ($normalized as $key => $item) {
            if (in_array(($results[$key]['status'] ?? ''), ['failed', 'skipped'], true)) {
                continue;
            }
            $remoteId = $remoteIds[$item['code']];
            $results[$key] = ($results[$key] ?? $this->successfulItem($item, 'verified')) + [
                'remote_id' => $remoteId,
            ];
        }

        $ordered = [];
        foreach (array_keys($normalized) as $key) {
            $ordered[] = $results[$key];
        }

        $this->checkpoint->clear($categoryTreeId, $items);

        return $ordered;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function validateBatch(int $categoryTreeId, array $items): void
    {
        if ($categoryTreeId <= 0) {
            throw new LocalizedException(__('Category Tree is required.'));
        }
        if ($items === []) {
            throw new LocalizedException(__('Category batch cannot be empty.'));
        }
        if (count($items) > self::MAX_BATCH_SIZE) {
            throw new LocalizedException(__(
                'Category batch contains %1 items; the maximum is %2.',
                count($items),
                self::MAX_BATCH_SIZE
            ));
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *     code: string,
     *     label: string,
     *     parent_code: string|null,
     *     sort_order: int,
     *     magento_category_id: int|null,
     *     pending_create: bool,
     *     allow_existing: bool
     * }
     */
    private function normalizeItem(array $item, string $code, bool $allowExisting): array
    {
        $extension = is_array($item['extension_data']['to_ergonode'] ?? null)
            ? $item['extension_data']['to_ergonode']
            : [];
        $parentCode = trim((string)($item['parent_code'] ?? ''));
        $magentoCategoryId = (int)($item['magento_category_id'] ?? 0);

        return [
            'code' => $code,
            'label' => trim((string)($extension['label'] ?? $item['label'] ?? $code)),
            'parent_code' => $parentCode !== '' ? $parentCode : null,
            'sort_order' => max(0, (int)($item['sort_order'] ?? 0)),
            'magento_category_id' => $magentoCategoryId > 0 ? $magentoCategoryId : null,
            'pending_create' => !empty($extension['pending_create']),
            'allow_existing' => $allowExisting,
        ];
    }

    /**
     * @param array<string, CategorySynchronizationResultInterface> $syncResults
     */
    private function throwForRateLimit(array $syncResults): void
    {
        $retryAfter = 0;
        $messages = [];
        foreach ($syncResults as $syncResult) {
            foreach ($syncResult->getResults() as $mutationResult) {
                if (!$mutationResult instanceof MutationResultInterface) {
                    continue;
                }
                foreach ($mutationResult->getErrors() as $error) {
                    $extensions = is_array($error['extensions'] ?? null) ? $error['extensions'] : [];
                    if (($extensions['failure_type'] ?? '') !== GraphQlRequestException::FAILURE_RATE_LIMIT) {
                        continue;
                    }
                    $retryAfter = max($retryAfter, (int)($extensions['retry_after_seconds'] ?? 5));
                    if (is_string($error['message'] ?? null) && $error['message'] !== '') {
                        $messages[] = $error['message'];
                    }
                }
            }
        }
        if ($retryAfter > 0) {
            throw new GraphQlRequestException(
                $messages !== []
                    ? implode(' ', array_unique($messages))
                    : (string)__('Ergonode limited GraphQL requests. Retry in %1 seconds.', $retryAfter),
                GraphQlRequestException::FAILURE_RATE_LIMIT,
                429,
                $retryAfter
            );
        }
    }

    /**
     * @param array{code: string, label: string} $item
     * @return array{code: string, label: string, status: string, message: string, magento_category_id: int|null}
     */
    private function resultItem(array $item, string $status, string $message): array
    {
        return [
            'code' => $item['code'],
            'label' => $item['label'],
            'status' => $status,
            'message' => $message,
            'magento_category_id' => $item['magento_category_id'] ?? null,
        ];
    }

    /**
     * @param array{code: string, label: string} $item
     * @return array{code: string, label: string, status: string, message: string, magento_category_id: int|null}
     */
    private function successfulItem(array $item, string $status): array
    {
        $message = match ($status) {
            'existing' => (string)__('Category already exists in Ergonode.'),
            'synchronized' => (string)__('Category has been synchronized with Ergonode.'),
            default => (string)__('Ergonode category identity has been verified.'),
        };

        return [
            'code' => $item['code'],
            'label' => $item['label'],
            'status' => $status,
            'message' => $message,
            'magento_category_id' => $item['magento_category_id'] ?? null,
        ];
    }
}
