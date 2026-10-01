<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;
use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\Core\Model\Report\ChangeReport;

class CategoryStructureSynchronizationProcess implements CategoryStructureSynchronizationProcessInterface
{
    public function __construct(
        private readonly CategoryStreamEligibility $eligibility,
        private readonly CategorySynchronizationLock $synchronizationLock,
        private readonly CategoryTreeStreamImporter $streamImporter,
        private readonly ChangeReport $changeReport,
        private readonly CategoryTreeDownloadScope $downloadScope
    ) {
    }

    public function execute(bool $resetCursor = false): array
    {
        $this->eligibility->assertCanSynchronize();
        $this->changeReport->reset();

        return $this->synchronizationLock->execute(
            fn (): array => $this->downloadScope->execute(fn (): array => $this->streamImporter->execute($resetCursor))
        );
    }
}
