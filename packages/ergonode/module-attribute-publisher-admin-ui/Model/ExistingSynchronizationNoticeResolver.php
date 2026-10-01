<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisherAdminUi\Model;

use Ergonode\Publisher\Api\Data\EntitySynchronizationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;

class ExistingSynchronizationNoticeResolver
{
    public function wasExisting(
        SynchronizationResultInterface $result,
        string $creationOperationPrefix
    ): bool {
        if ($result instanceof EntitySynchronizationResultInterface
            && $result->getStatus() === EntitySynchronizationResultInterface::STATUS_NOOP
        ) {
            return true;
        }

        $mutationResults = $result->getResults();
        if ($mutationResults === []) {
            return false;
        }
        foreach ($mutationResults as $mutationResult) {
            $operationKey = (string)($mutationResult->getOperation()->getMetadata()['operation_key'] ?? '');
            if (str_starts_with($operationKey, $creationOperationPrefix)) {
                return false;
            }
        }

        return true;
    }
}
