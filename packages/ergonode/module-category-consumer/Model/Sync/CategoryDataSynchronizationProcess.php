<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;
use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use Ergonode\Core\Model\Report\ChangeReport;

class CategoryDataSynchronizationProcess implements CategoryDataSynchronizationProcessInterface
{
    public function __construct(
        private readonly CategoryStreamEligibility $eligibility,
        private readonly CategorySynchronizationLock $synchronizationLock,
        private readonly CategoryEntityStreamImporter $streamImporter,
        private readonly ChangeReport $changeReport,
        private readonly CategoryTreeDownloadScope $downloadScope,
        private readonly CategoryCacheInvalidator $cacheInvalidator
    ) {
    }

    public function execute(bool $resetCursor = false): array
    {
        $this->eligibility->assertCanSynchronize(data: true);
        $this->changeReport->reset();

        return $this->synchronizationLock->execute(
            fn (): array => $this->cacheInvalidator->defer(
                fn (): array => $this->downloadScope->execute(
                    fn (): array => $this->streamImporter->execute($resetCursor)
                )
            )
        );
    }
}
